<?php
file_put_contents('assets/css/style.css', "
/* Tablas Compactas MCI */
.table-compact th, .table-compact td { padding: 0.35rem 0.4rem !important; font-size: 0.8rem !important; }
.table-compact th { font-size: 0.72rem !important; letter-spacing: 0px !important; }
", FILE_APPEND);
echo "CSS appended";
