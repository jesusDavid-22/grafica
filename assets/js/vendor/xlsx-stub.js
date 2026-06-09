/**
 * XLSX Stub - Exporta funcionalidad básica de Excel
 * Este archivo proporciona las funciones esenciales para generar archivos Excel sin dependencias externas
 */

if (typeof XLSX === 'undefined') {
    window.XLSX = {};
}

XLSX.utils = {
    book_new: function() {
        return {
            SheetNames: [],
            Sheets: {},
            Props: {}
        };
    },
    
    aoa_to_sheet: function(data, opts) {
        const sheet = {};
        const range = { s: { c: 0, r: 0 }, e: { c: 0, r: 0 } };
        
        data.forEach((row, rowIdx) => {
            if (row && row.length > 0) {
                if (rowIdx === 0) range.e.r = 0;
                range.e.r = Math.max(range.e.r, rowIdx);
                
                row.forEach((cell, colIdx) => {
                    range.e.c = Math.max(range.e.c, colIdx);
                    
                    const cellRef = XLSX.utils.encode_cell({ r: rowIdx, c: colIdx });
                    sheet[cellRef] = {
                        v: cell,
                        t: typeof cell === 'number' ? 'n' : 's'
                    };
                });
            }
        });
        
        sheet['!ref'] = XLSX.utils.encode_range(range);
        return sheet;
    },
    
    book_append_sheet: function(wb, ws, name) {
        wb.SheetNames.push(name);
        wb.Sheets[name] = ws;
    },
    
    encode_cell: function(cell) {
        const col = String.fromCharCode(65 + (cell.c % 26)) + (cell.c >= 26 ? cell.c - 25 : '');
        return col + (cell.r + 1);
    },
    
    encode_range: function(range) {
        return XLSX.utils.encode_cell(range.s) + ':' + XLSX.utils.encode_cell(range.e);
    }
};

XLSX.writeFile = function(wb, filename) {
    // Crear CSV a partir del workbook
    let csv = '';
    
    wb.SheetNames.forEach((sheetName, sheetIdx) => {
        if (sheetIdx > 0) csv += '\n\n---\n\n';
        
        const sheet = wb.Sheets[sheetName];
        const range = sheet['!ref'];
        
        if (!range) return;
        
        const cells = Object.keys(sheet).filter(k => k !== '!ref' && k !== '!cols');
        const rows = {};
        
        cells.forEach(cell => {
            const match = cell.match(/^([A-Z]+)(\d+)$/);
            if (match) {
                const row = parseInt(match[2]) - 1;
                if (!rows[row]) rows[row] = [];
                rows[row].push({ cell, value: sheet[cell].v });
            }
        });
        
        // Ordenar y generar CSV
        Object.keys(rows).sort((a, b) => parseInt(a) - parseInt(b)).forEach(rowKey => {
            const row = rows[rowKey];
            row.sort((a, b) => {
                const colA = a.cell.charCodeAt(0);
                const colB = b.cell.charCodeAt(0);
                return colA - colB;
            });
            csv += row.map(r => {
                const val = r.value;
                return typeof val === 'string' && val.includes(',') ? `"${val}"` : val;
            }).join(',') + '\n';
        });
    });
    
    // Descargar
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename.replace('.xlsx', '.csv');
    link.click();
};
