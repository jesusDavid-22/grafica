/**
 * Funciones de Edición y Eliminación de Transacciones
 * Módulo separado para manejar operaciones en la tabla de transacciones
 */

/**
 * Abre el modal de edición para una transacción
 */
function editTransaction(txId, txType) {
    // Verificar que los campos existan
    if (!document.getElementById('edit-tx-id') || 
        !document.getElementById('edit-concepto') ||
        !document.getElementById('edit-monto') ||
        !document.getElementById('edit-fecha')) {
        showToast('Error: Modal de edición no encontrado. Recarga la página.', 'danger', 5000);
        return;
    }
    
    // Buscar la transacción en el array de transacciones actuales
    const transaction = currentTransactions.find(tx => parseInt(tx.id) === parseInt(txId));
    
    if (!transaction) {
        showToast('Transacción no encontrada. Intenta recargar los datos.', 'danger', 4500);
        console.warn('Transacción no encontrada:', { txId, txType, transacciones: currentTransactions });
        return;
    }
    
    // Llenar los campos del modal
    document.getElementById('edit-tx-id').value = txId;
    document.getElementById('edit-tx-tipo').value = txType;
    document.getElementById('edit-concepto').value = transaction.concepto || '';
    document.getElementById('edit-monto').value = transaction.monto || '';
    document.getElementById('edit-fecha').value = transaction.fecha ? transaction.fecha.split(' ')[0] : '';
    
    // Mostrar/ocultar el campo de categoría según el tipo
    const categoryGroup = document.getElementById('edit-categoria-group');
    if (txType === 'gasto') {
        categoryGroup.style.display = 'block';
        
        // Cargar categorías en el select
        fetch('api.php?action=get_categorias')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const selectElement = document.getElementById('edit-categoria');
                    selectElement.innerHTML = '';
                    data.categorias.forEach(cat => {
                        const option = document.createElement('option');
                        option.value = cat.id;
                        option.textContent = cat.nombre;
                        // Marcar como seleccionado si coincide
                        if (parseInt(cat.id) === parseInt(transaction.categoria_id)) {
                            option.selected = true;
                        }
                        selectElement.appendChild(option);
                    });
                    // Si no hay seleccionado, seleccionar el primero
                    if (selectElement.value === '') {
                        selectElement.selectedIndex = 0;
                    }
                }
            })
            .catch(error => console.error('Error al cargar categorías:', error));
    } else {
        categoryGroup.style.display = 'none';
    }
    
    // Actualizar título del modal
    const titleEl = document.getElementById('modal-edit-title');
    if (txType === 'gasto') {
        titleEl.textContent = 'Editar Gasto';
    } else {
        titleEl.textContent = 'Editar Ingreso';
    }
    
    // Abrir el modal
    openModal('modal-editar-transaccion');
}

/**
 * Maneja el envío del formulario de edición
 */
function handleEditTransactionSubmit(event) {
    event.preventDefault();
    
    const id = document.getElementById('edit-tx-id').value;
    const tipo = document.getElementById('edit-tx-tipo').value;
    const concepto = document.getElementById('edit-concepto').value;
    const monto = document.getElementById('edit-monto').value;
    const fecha = document.getElementById('edit-fecha').value;
    
    let action = '';
    let payload = {
        id: id,
        concepto: concepto,
        monto: monto,
        fecha: fecha
    };
    
    if (tipo === 'gasto') {
        action = 'edit_gasto';
        const categoriaId = document.getElementById('edit-categoria').value;
        payload.categoria_id = categoriaId;
    } else {
        action = 'edit_ingreso';
    }
    
    payload.action = action;
    
    // Enviar a la API
    fetch('api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success', 3000);
            closeModal('modal-editar-transaccion');
            
            // Refrescar dashboard
            refreshDashboardData();
        } else {
            showToast(data.message || 'Error al actualizar la transacción.', 'danger', 4000);
        }
    })
    .catch(error => {
        console.error('Error al editar transacción:', error);
        showToast('Error de conexión con la base de datos.', 'danger', 4000);
    });
}

/**
 * Pide confirmación antes de eliminar una transacción usando modal personalizado
 */
function deleteTransactionConfirm(txId, txType) {
    const transaction = currentTransactions.find(tx => parseInt(tx.id) === parseInt(txId));
    
    if (!transaction) {
        showToast('Transacción no encontrada.', 'danger');
        return;
    }
    
    // Guardar datos en campos ocultos
    document.getElementById('confirm-delete-tx-id').value = txId;
    document.getElementById('confirm-delete-tx-type').value = txType;
    
    // Llenar información en el modal
    const tipoEtiqueta = txType === 'gasto' ? '💸 Gasto' : '💰 Ingreso';
    document.getElementById('confirm-delete-concepto').textContent = transaction.concepto;
    document.getElementById('confirm-delete-monto').textContent = `$${transaction.monto.toLocaleString('es-MX', { minimumFractionDigits: 2 })}`;
    document.getElementById('confirm-delete-fecha').textContent = transaction.fecha;
    document.getElementById('confirm-delete-tipo').textContent = tipoEtiqueta;
    
    // Abrir modal
    openModal('modal-confirmar-eliminacion');
}

/**
 * Confirma la eliminación desde el modal
 */
function confirmDeleteFromModal() {
    const txId = document.getElementById('confirm-delete-tx-id').value;
    const txType = document.getElementById('confirm-delete-tx-type').value;
    
    closeModal('modal-confirmar-eliminacion');
    deleteTransaction(txId, txType);
}

/**
 * Elimina una transacción
 */
function deleteTransaction(txId, txType) {
    const action = txType === 'gasto' ? 'delete_gasto' : 'delete_ingreso';
    
    const payload = {
        action: action,
        id: txId
    };
    
    fetch('api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success', 3000);
            
            // Refrescar dashboard
            refreshDashboardData();
        } else {
            showToast(data.message || 'Error al eliminar la transacción.', 'danger', 4000);
        }
    })
    .catch(error => {
        console.error('Error al eliminar transacción:', error);
        showToast('Error de conexión con la base de datos.', 'danger', 4000);
    });
}
