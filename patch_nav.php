<?php
$file = 'assets/js/app.js';
$content = file_get_contents($file);
$content = preg_replace('/refreshDashboardData\(\);\s*\/\/[^\n]*\n/', 'if (targetId === "dashboard") { refreshDashboardData(); window.scrollTo({ top: 0, behavior: "smooth" }); } else { setTimeout(() => { const section = document.getElementById(targetId); if (section) { const offset = 80; const bodyRect = document.body.getBoundingClientRect().top; const elementRect = section.getBoundingClientRect().top; window.scrollTo({ top: elementRect - bodyRect - offset, behavior: "smooth" }); } }, 50); }' . "\n", $content);
file_put_contents($file, $content);
echo "Patched app.js";
