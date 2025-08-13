-- 1) Базовые таблицы
CREATE TABLE IF NOT EXISTS categories (
                                          id SERIAL PRIMARY KEY,
                                          name TEXT NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS products (
                                        id SERIAL PRIMARY KEY,
                                        category_id INT NOT NULL REFERENCES categories(id) ON DELETE RESTRICT,
                                        name TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS orders (
                                      id BIGSERIAL PRIMARY KEY,
                                      product_id INT NOT NULL REFERENCES products(id) ON DELETE RESTRICT,
                                      quantity INT NOT NULL DEFAULT 1 CHECK (quantity > 0),
                                      created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);


-- Индексы для базовой работы
CREATE INDEX IF NOT EXISTS idx_orders_created_at_desc ON orders (created_at DESC);
CREATE INDEX IF NOT EXISTS idx_products_category_id ON products (category_id);

-- Демоданные (безопасно и идемпотентно)
INSERT INTO categories (name) VALUES ('Books'), ('Games'), ('Electronics')
ON CONFLICT (name) DO NOTHING;

-- 5 продуктов на каждую категорию
INSERT INTO products (category_id, name)
SELECT c.id, c.name || ' Product ' || g.num
FROM categories c
         CROSS JOIN generate_series(1,5) AS g(num)
ON CONFLICT DO NOTHING;

-- 2) Быстрый путь Gamma: окно последних 100 заказов и агрегаты

-- Буфер последних 100 заказов
CREATE TABLE IF NOT EXISTS orders_recent (
                                             order_id BIGINT PRIMARY KEY,
                                             created_at TIMESTAMPTZ NOT NULL,
                                             category_id INT NOT NULL,
                                             quantity INT NOT NULL CHECK (quantity > 0)
);
CREATE INDEX IF NOT EXISTS idx_orders_recent_created_at ON orders_recent (created_at, order_id);

-- Агрегаты по категориям для "последних 100"
CREATE TABLE IF NOT EXISTS stats_recent (
                                            category_id INT PRIMARY KEY,
                                            qty_sum BIGINT NOT NULL DEFAULT 0
);

-- Метаданные окна
CREATE TABLE IF NOT EXISTS stats_meta (
                                          id SMALLINT PRIMARY KEY,
                                          window_size INT NOT NULL DEFAULT 0,
                                          first_ts TIMESTAMPTZ,
                                          last_ts TIMESTAMPTZ
);
INSERT INTO stats_meta (id, window_size, first_ts, last_ts)
VALUES (1, 0, NULL, NULL)
ON CONFLICT (id) DO NOTHING;

-- 3) Функция-триггер: поддержание окна и агрегатов при вставке заказа
CREATE OR REPLACE FUNCTION trg_after_insert_orders_update_recent()
    RETURNS TRIGGER
    LANGUAGE plpgsql
AS $$
DECLARE
    v_category_id INT;
    v_quantity INT;
    v_created_at TIMESTAMPTZ;
    v_overflow INT;
    v_old_order RECORD;
BEGIN
    -- Определяем категорию и количество для нового заказа
    SELECT p.category_id, NEW.quantity, NEW.created_at
    INTO v_category_id, v_quantity, v_created_at
    FROM products p
    WHERE p.id = NEW.product_id;

    -- Сериализуем обновление окна: блокируем единственную строку меты
    PERFORM 1 FROM stats_meta WHERE id = 1 FOR UPDATE;

    -- Вставляем новый заказ в буфер (идемпотентно)
    INSERT INTO orders_recent (order_id, created_at, category_id, quantity)
    VALUES (NEW.id, v_created_at, v_category_id, v_quantity)
    ON CONFLICT (order_id) DO NOTHING;

    -- Инкремент агрегата по категории
    INSERT INTO stats_recent (category_id, qty_sum)
    VALUES (v_category_id, v_quantity)
    ON CONFLICT (category_id) DO UPDATE SET qty_sum = stats_recent.qty_sum + EXCLUDED.qty_sum;

    -- Обновляем метаданные окна
    UPDATE stats_meta
    SET window_size = window_size + 1,
        last_ts = v_created_at
    WHERE id = 1;

    -- Если размер окна превысил 100 — удаляем самые старые и декрементим агрегаты
    SELECT GREATEST((SELECT window_size FROM stats_meta WHERE id = 1) - 100, 0)
    INTO v_overflow;

    IF v_overflow > 0 THEN
        FOR v_old_order IN
            SELECT order_id, created_at, category_id, quantity
            FROM orders_recent
            ORDER BY created_at ASC, order_id ASC
            LIMIT v_overflow
            LOOP
                UPDATE stats_recent
                SET qty_sum = qty_sum - v_old_order.quantity
                WHERE category_id = v_old_order.category_id;

                DELETE FROM orders_recent WHERE order_id = v_old_order.order_id;
            END LOOP;

        -- Удаляем пустые строки агрегатов (косметика)
        DELETE FROM stats_recent WHERE qty_sum <= 0;

        UPDATE stats_meta
        SET window_size = window_size - v_overflow
        WHERE id = 1;
    END IF;

    -- Пересчитываем first_ts по текущему минимуму в буфере
    UPDATE stats_meta m
    SET first_ts = sub.min_ts
    FROM (SELECT MIN(created_at) AS min_ts FROM orders_recent) AS sub
    WHERE m.id = 1;

    RETURN NULL;
END;
$$;

-- 4) Триггер на таблицу orders
DROP TRIGGER IF EXISTS after_insert_orders_update_recent ON orders;

CREATE TRIGGER after_insert_orders_update_recent
    AFTER INSERT ON orders
    FOR EACH ROW
EXECUTE FUNCTION trg_after_insert_orders_update_recent();

-- 5) Бэкфилл окна и агрегатов из последних 100 заказов (однократно/безопасно)
DO $$
    DECLARE
        v_window_size INT;
    BEGIN
        -- Выполняем бэкфилл только если окно пустое (первый запуск)
        SELECT window_size INTO v_window_size FROM stats_meta WHERE id = 1;
        IF v_window_size IS NULL OR v_window_size = 0 THEN
            -- Очищаем служебные таблицы
            TRUNCATE TABLE orders_recent;
            TRUNCATE TABLE stats_recent;

            -- Заполняем последние 100 заказов с категориями
            INSERT INTO orders_recent (order_id, created_at, category_id, quantity)
            SELECT o.id, o.created_at, p.category_id, o.quantity
            FROM orders o
                     JOIN products p ON p.id = o.product_id
            ORDER BY o.created_at DESC, o.id DESC
            LIMIT 100;

            -- Пересобираем агрегаты
            INSERT INTO stats_recent (category_id, qty_sum)
            SELECT category_id, SUM(quantity)::BIGINT
            FROM orders_recent
            GROUP BY category_id
            ON CONFLICT (category_id) DO UPDATE SET qty_sum = EXCLUDED.qty_sum;

            -- Обновляем мету
            UPDATE stats_meta
            SET window_size = (SELECT COUNT(*) FROM orders_recent),
                first_ts = (SELECT MIN(created_at) FROM orders_recent),
                last_ts  = (SELECT MAX(created_at) FROM orders_recent)
            WHERE id = 1;
        END IF;
    END $$;