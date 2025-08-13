(() => {
    const runBtn = document.getElementById('run');
    const nInput = document.getElementById('n');
    const betaResult = document.getElementById('betaResult');
    const statsDiv = document.getElementById('stats');

    runBtn.addEventListener('click', async () => {
        const n = parseInt(nInput.value || '1000', 10);
        betaResult.textContent = 'Запуск...';
        try {
            const resp = await fetch('./beta.php?n=' + encodeURIComponent(n));
            const json = await resp.json();
            betaResult.textContent = JSON.stringify(json, null, 2);
        } catch (e) {
            betaResult.textContent = 'Ошибка: ' + e;
        }
    });

    async function loadStats() {
        try {
            const resp = await fetch('./gamma.php?_=' + Date.now());
            const data = await resp.json();
            const categories = (data.categories || [])
                .map(
                    (c) =>
                        `<tr><td>${c.category_id}</td><td>${c.category_name}</td><td>${c.quantity}</td></tr>`
                )
                .join('');
            statsDiv.innerHTML = `
        <div>Заказов в выборке: ${data.orders_count}</div>
        <div>Дельта времени (сек): ${data.time_delta_sec}</div>
        <div>Сгенерировано: ${data.generated_at}</div>
        <table>
          <thead><tr><th>ID категории</th><th>Категория</th><th>Кол-во</th></tr></thead>
          <tbody>${categories}</tbody>
        </table>
      `;
        } catch (e) {
            statsDiv.textContent = 'Ошибка загрузки статистики: ' + e;
        }
    }

    loadStats();
    setInterval(loadStats, 1000);
})();