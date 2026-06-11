const puppeteer = require('puppeteer');

(async () => {
  const browser = await puppeteer.launch();
  const page = await browser.newPage();
  
  // Establecemos un viewport ancho (landscape)
  await page.setViewport({ width: 1200, height: 800 });

  await page.goto('http://localhost/grafica/index.php', { waitUntil: 'networkidle0' });

  // Cambiamos el mes a "Mayo" o "Año" para que haya datos de prueba
  await page.evaluate(() => {
    document.getElementById('global-period-picker').value = '2026-05';
    // Despachar el evento 'change' para que actualice todo
    document.getElementById('global-period-picker').dispatchEvent(new Event('change'));
  });

  // Esperar a que las gráficas se animen y rendericen
  await new Promise(r => setTimeout(r, 2000));

  // Generar PDF simulando window.print
  await page.emulateMediaType('print');
  await page.pdf({
    path: 'C:\\Users\\jesus\\.gemini\\antigravity-ide\\brain\\171460a0-00a2-4561-be18-d161fc18f51f\\test_print.pdf',
    format: 'A4',
    landscape: true,
    printBackground: true,
    margin: { top: '10mm', right: '10mm', bottom: '10mm', left: '10mm' }
  });

  await browser.close();
  console.log("PDF generado en test_print.pdf");
})();
