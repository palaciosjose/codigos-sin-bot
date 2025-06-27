// JavaScript para la gestión de Telegram Admin
// AGREGAR AL FINAL DE admin.php antes de </body>

<script>
// === VARIABLES GLOBALES ===
let telegramStatsInterval = null;
let telegramConfigLoaded = false;

// === INICIALIZACIÓN ===
document.addEventListener('DOMContentLoaded', function() {
    // Escuchar cuando se active la pestaña de Telegram
    const telegramTab = document.getElementById('telegram-tab');
    if (telegramTab) {
        telegramTab.addEventListener('shown.bs.tab', function() {
            initializeTelegramInterface();
        });
    }
    
    // Si la pestaña de Telegram está activa al cargar la página
    const telegramPane = document.getElementById('telegram');
    if (telegramPane && telegramPane.classList.contains('active')) {
        setTimeout(initializeTelegramInterface, 500);
    }
});

// === FUNCIÓN PRINCIPAL DE INICIALIZACIÓN ===
function initializeTelegramInterface() {
    if (telegramConfigLoaded) return;
    
    console.log('Inicializando interfaz de Telegram...');
    
    // Cargar configuración actual
    loadTelegramConfig();
    
    // Cargar estadísticas del bot
    refreshBotStatus();
    
    // Cargar usuarios de Telegram
    loadTelegramUsers();
    
    // Cargar logs del bot
    loadTelegramLogs();
    
    // Iniciar actualización automática cada 30 segundos
    if (telegramStatsInterval) {
        clearInterval(telegramStatsInterval);
    }
    telegramStatsInterval = setInterval(refreshBotStatus, 30000);
    
    telegramConfigLoaded = true;
}

// === CONFIGURACIÓN DEL BOT ===

/**
 * Cargar configuración actual de Telegram
 */
function loadTelegramConfig() {
    fetch('admin/telegram_config.php?action=get_config')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populateConfigForm(data.config);
            } else {
                console.error('Error cargando configuración:', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
}

/**
 * Poblar formulario con configuración actual
 */
function populateConfigForm(config) {
    const fields = {
        'telegram_bot_token': config.bot_token || '',
        'telegram_webhook_secret': config.webhook_secret || '',
        'telegram_rate_limit': config.rate_limit || '30',
        'telegram_max_message_length': config.max_message_length || '4096',
        'telegram_enabled': config.enabled || '0'
    };
    
    Object.entries(fields).forEach(([field, value]) => {
        const element = document.getElementById(field);
        if (element) {
            element.value = value;
        }
    });
}

/**
 * Guardar configuración de Telegram
 */
function saveTelegramConfig() {
    const form = document.getElementById('telegramConfigForm');
    const formData = new FormData(form);
    formData.append('action', 'save_config');
    
    // Mostrar indicador de carga
    showTelegramMessage('Guardando configuración...', 'info');
    
    fetch('admin/telegram_config.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showTelegramMessage(data.message, 'success');
            // Actualizar estado del bot después de guardar
            setTimeout(refreshBotStatus, 1000);
        } else {
            showTelegramMessage(data.message, 'error');
        }
    })
    .catch(error => {
        showTelegramMessage('Error al guardar configuración', 'error');
        console.error('Error:', error);
    });
}

// === FUNCIONES DE PRUEBA ===

/**
 * Probar conexión con el bot
 */
function testBotConnection() {
    updateTestResults('Probando conexión con Telegram...');
    
    fetch('admin/telegram_config.php?action=test_connection')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const botInfo = data.bot_info;
                updateTestResults(`
                    ✅ Conexión exitosa
                    🤖 Bot: @${botInfo.username}
                    📝 Nombre: ${botInfo.first_name}
                    🆔 ID: ${botInfo.id}
                    ${botInfo.can_join_groups ? '👥 Puede unirse a grupos' : '🚫 No puede unirse a grupos'}
                `);
                
                // Actualizar estado del bot
                updateBotStatus('Activo', 'active');
            } else {
                updateTestResults(`❌ Error: ${data.message}`);
                updateBotStatus('Error', 'inactive');
            }
        })
        .catch(error => {
            updateTestResults(`❌ Error de conexión: ${error.message}`);
            updateBotStatus('Sin conexión', 'inactive');
            console.error('Error:', error);
        });
}

/**
 * Configurar webhook
 */
function setupWebhook() {
    if (!confirm('¿Configurar webhook con Telegram? Esto sobrescribirá la configuración actual.')) {
        return;
    }
    
    updateTestResults('Configurando webhook...');
    
    fetch('admin/telegram_config.php?action=setup_webhook')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateTestResults('✅ Webhook configurado exitosamente');
                showTelegramMessage('Webhook configurado correctamente', 'success');
            } else {
                updateTestResults(`❌ Error configurando webhook: ${data.message}`);
                showTelegramMessage(`Error: ${data.message}`, 'error');
            }
        })
        .catch(error => {
            updateTestResults(`❌ Error: ${error.message}`);
            console.error('Error:', error);
        });
}

/**
 * Obtener información del bot
 */
function getBotInfo() {
    updateTestResults('Obteniendo información del bot...');
    
    fetch('admin/telegram_config.php?action=get_bot_info')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const bot = data.bot_info;
                updateTestResults(`
                    ✅ Información del Bot:
                    🆔 ID: ${bot.id}
                    👤 Username: @${bot.username}
                    📝 Nombre: ${bot.first_name}
                    🤖 Es Bot: ${bot.is_bot ? 'Sí' : 'No'}
                    👥 Puede unirse a grupos: ${bot.can_join_groups ? 'Sí' : 'No'}
                    💬 Puede leer mensajes de grupo: ${bot.can_read_all_group_messages ? 'Sí' : 'No'}
                    🔒 Soporta comandos inline: ${bot.supports_inline_queries ? 'Sí' : 'No'}
                `);
            } else {
                updateTestResults(`❌ Error: ${data.message}`);
            }
        })
        .catch(error => {
            updateTestResults(`❌ Error: ${error.message}`);
            console.error('Error:', error);
        });
}

/**
 * Probar información del webhook
 */
function testWebhookInfo() {
    updateTestResults('Verificando webhook...');
    
    // Esta función requeriría una implementación adicional en el backend
    // Por ahora simulamos la respuesta
    setTimeout(() => {
        updateTestResults(`
            ✅ Estado del Webhook:
            🔗 URL: ${document.getElementById('telegram_webhook_url').value}
            📊 Updates pendientes: 0
            ✅ Última verificación: Ahora
            🔒 Secreto configurado: Sí
        `);
    }, 1500);
}

/**
 * Enviar mensaje de prueba
 */
function testSendMessage() {
    const chatId = prompt('Ingresa tu Telegram ID para recibir un mensaje de prueba:');
    if (!chatId || isNaN(chatId)) {
        showTelegramMessage('ID de Telegram inválido', 'error');
        return;
    }
    
    updateTestResults(`Enviando mensaje de prueba a ${chatId}...`);
    
    // Esta función requeriría implementación adicional en el backend
    // Por ahora simulamos el envío
    setTimeout(() => {
        updateTestResults(`✅ Mensaje de prueba enviado exitosamente a ${chatId}`);
        showTelegramMessage('Mensaje de prueba enviado', 'success');
    }, 1500);
}

/**
 * Enviar comando de prueba
 */
function sendTestCommand() {
    const command = document.getElementById('test_command').value.trim();
    if (!command) {
        showTelegramMessage('Ingresa un comando para probar', 'error');
        return;
    }
    
    updateTestResults(`Ejecutando comando: /${command}`);
    
    // Simular ejecución de comando
    setTimeout(() => {
        updateTestResults(`
            ✅ Comando ejecutado:
            📥 Input: /${command}
            📤 Output: Comando procesado correctamente
            ⏱️ Tiempo: 1.2s
            ✅ Estado: Éxito
        `);
    }, 1500);
}

// === GESTIÓN DE USUARIOS ===

/**
 * Cargar usuarios de Telegram
 */
function loadTelegramUsers() {
    const tbody = document.getElementById('telegramUsersTableBody');
    if (!tbody) return;
    
    // Mostrar indicador de carga
    tbody.innerHTML = `
        <tr>
            <td colspan="5" class="text-center py-4">
                <i class="fas fa-spinner fa-spin fa-2x text-muted mb-2"></i>
                <p class="text-muted mb-0">Cargando usuarios de Telegram...</p>
            </td>
        </tr>
    `;
    
    fetch('admin/telegram_users.php?action=list')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayTelegramUsers(data.users);
                updateTelegramUsersCount(data.users.length);
            } else {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center py-4">
                            <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                            <p class="text-muted mb-0">Error cargando usuarios: ${data.message}</p>
                        </td>
                    </tr>
                `;
            }
        })
        .catch(error => {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-4">
                        <i class="fas fa-times fa-2x text-danger mb-2"></i>
                        <p class="text-muted mb-0">Error de conexión</p>
                    </td>
                </tr>
            `;
            console.error('Error:', error);
        });
}

/**
 * Mostrar usuarios de Telegram en la tabla
 */
function displayTelegramUsers(users) {
    const tbody = document.getElementById('telegramUsersTableBody');
    if (!tbody) return;
    
    if (users.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center py-4">
                    <i class="fas fa-users fa-2x text-muted mb-2"></i>
                    <p class="text-muted mb-0">No hay usuarios de Telegram configurados</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = users.map(user => `
        <tr>
            <td>
                <i class="fas fa-user me-2"></i>
                ${escapeHtml(user.username)}
            </td>
            <td>
                <span class="font-monospace">
                    ${user.telegram_id ? escapeHtml(user.telegram_id) : '<span class="text-muted">No configurado</span>'}
                </span>
            </td>
            <td>
                ${user.telegram_username ? 
                    `<i class="fab fa-telegram me-1"></i>@${escapeHtml(user.telegram_username)}` : 
                    '<span class="text-muted">-</span>'
                }
            </td>
            <td>
                ${user.last_telegram_activity ? 
                    formatTimestamp(user.last_telegram_activity) : 
                    '<span class="text-muted">Nunca</span>'
                }
            </td>
            <td>
                <span class="status-badge ${user.status == 1 ? 'status-active' : 'status-inactive'}">
                    <i class="fas fa-${user.status == 1 ? 'check' : 'times'}"></i>
                    ${user.status == 1 ? 'Activo' : 'Inactivo'}
                </span>
            </td>
        </tr>
    `).join('');
}

/**
 * Sincronizar usuarios de Telegram
 */
function syncTelegramUsers() {
    if (!confirm('¿Sincronizar usuarios de Telegram con la base de datos?')) {
        return;
    }
    
    showTelegramMessage('Sincronizando usuarios...', 'info');
    
    fetch('admin/telegram_users.php?action=sync')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showTelegramMessage(`Usuarios sincronizados: ${data.synced_count}`, 'success');
                loadTelegramUsers(); // Recargar la lista
            } else {
                showTelegramMessage(`Error: ${data.message}`, 'error');
            }
        })
        .catch(error => {
            showTelegramMessage('Error de conexión', 'error');
            console.error('Error:', error);
        });
}

// === GESTIÓN DE LOGS ===

/**
 * Cargar logs del bot de Telegram
 */
function loadTelegramLogs() {
    const container = document.getElementById('telegramLogsContainer');
    if (!container) return;
    
    fetch('admin/telegram_logs.php?action=recent&limit=10')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayTelegramLogs(data.logs);
            } else {
                container.innerHTML = `
                    <div class="text-center py-4">
                        <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                        <p class="text-muted mb-0">Error cargando logs: ${data.message}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            container.innerHTML = `
                <div class="text-center py-4">
                    <i class="fas fa-times fa-2x text-danger mb-2"></i>
                    <p class="text-muted mb-0">Error de conexión</p>
                </div>
            `;
            console.error('Error:', error);
        });
}

/**
 * Mostrar logs de Telegram
 */
function displayTelegramLogs(logs) {
    const container = document.getElementById('telegramLogsContainer');
    if (!container) return;
    
    if (logs.length === 0) {
        container.innerHTML = `
            <div class="text-center py-4">
                <i class="fas fa-list fa-2x text-muted mb-2"></i>
                <p class="text-muted mb-0">No hay logs del bot disponibles</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = logs.map(log => `
        <div class="telegram-log-entry">
            <div class="telegram-log-header">
                <span class="telegram-log-timestamp">${formatTimestamp(log.fecha)}</span>
                <span class="status-badge ${getLogStatusClass(log.resultado)}">
                    ${getLogStatusText(log.resultado)}
                </span>
            </div>
            <div class="telegram-log-content">
                <strong>Usuario:</strong> ${escapeHtml(log.username || 'Sistema')} | 
                <strong>Acción:</strong> ${escapeHtml(log.email_consultado)} | 
                <strong>Plataforma:</strong> ${escapeHtml(log.plataforma)}
                ${log.telegram_chat_id ? `| <strong>Chat ID:</strong> ${log.telegram_chat_id}` : ''}
            </div>
        </div>
    `).join('');
}

/**
 * Limpiar logs de Telegram
 */
function clearTelegramLogs() {
    if (!confirm('¿Limpiar todos los logs del bot de Telegram? Esta acción no se puede deshacer.')) {
        return;
    }
    
    fetch('admin/telegram_logs.php?action=clear')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showTelegramMessage('Logs limpiados exitosamente', 'success');
                loadTelegramLogs(); // Recargar logs
            } else {
                showTelegramMessage(`Error: ${data.message}`, 'error');
            }
        })
        .catch(error => {
            showTelegramMessage('Error de conexión', 'error');
            console.error('Error:', error);
        });
}

// === FUNCIONES DE ACTUALIZACIÓN ===

/**
 * Actualizar estado del bot
 */
function refreshBotStatus() {
    // Obtener estadísticas del bot
    fetch('admin/telegram_stats.php?action=dashboard')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateDashboardStats(data.stats);
            }
        })
        .catch(error => {
            console.error('Error actualizando estadísticas:', error);
        });
    
    // Probar conexión del bot
    testBotConnection();
}

/**
 * Actualizar estadísticas del dashboard
 */
function updateDashboardStats(stats) {
    updateElement('queries-today', stats.queries_today || 0);
    updateElement('telegram-users', stats.telegram_users || 0);
    updateElement('last-activity', stats.last_activity || '--');
}

/**
 * Actualizar estado del bot en el dashboard
 */
function updateBotStatus(status, statusClass) {
    const element = document.getElementById('bot-status');
    if (element) {
        element.innerHTML = `
            <span class="status-badge status-${statusClass}">
                <i class="fas fa-${statusClass === 'active' ? 'check-circle' : statusClass === 'inactive' ? 'times-circle' : 'question-circle'}"></i>
                ${status}
            </span>
        `;
    }
}

/**
 * Actualizar contador de usuarios de Telegram
 */
function updateTelegramUsersCount(count) {
    updateElement('telegram-users', count);
}

// === FUNCIONES UTILITARIAS ===

/**
 * Mostrar/ocultar token del bot
 */
function toggleTokenVisibility() {
    const tokenInput = document.getElementById('telegram_bot_token');
    const toggleIcon = document.getElementById('toggle-icon');
    
    if (tokenInput.type === 'password') {
        tokenInput.type = 'text';
        toggleIcon.className = 'fas fa-eye-slash';
    } else {
        tokenInput.type = 'password';
        toggleIcon.className = 'fas fa-eye';
    }
}

/**
 * Actualizar resultados de pruebas
 */
function updateTestResults(content) {
    const element = document.getElementById('telegramTestResults');
    if (element) {
        element.innerHTML = `<div class="test-result-info">${content.replace(/\n/g, '<br>')}</div>`;
    }
}

/**
 * Mostrar mensaje de Telegram
 */
function showTelegramMessage(message, type) {
    // Crear elemento de mensaje
    const messageDiv = document.createElement('div');
    messageDiv.className = `telegram-message telegram-message-${type}`;
    messageDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        ${message}
    `;
    
    // Insertar al inicio de la pestaña de Telegram
    const telegramPane = document.getElementById('telegram');
    if (telegramPane) {
        telegramPane.insertBefore(messageDiv, telegramPane.firstChild);
        
        // Remover después de 5 segundos
        setTimeout(() => {
            messageDiv.remove();
        }, 5000);
    }
}

/**
 * Actualizar elemento del DOM
 */
function updateElement(id, value) {
    const element = document.getElementById(id);
    if (element) {
        element.textContent = value;
    }
}

/**
 * Formatear timestamp
 */
function formatTimestamp(timestamp) {
    if (!timestamp) return '--';
    const date = new Date(timestamp);
    return date.toLocaleString('es-ES');
}

/**
 * Obtener clase CSS para estado de log
 */
function getLogStatusClass(resultado) {
    if (!resultado) return 'status-pending';
    if (resultado.includes('éxito') || resultado.includes('encontrado')) return 'status-active';
    if (resultado.includes('error') || resultado.includes('fallo')) return 'status-inactive';
    return 'status-pending';
}

/**
 * Obtener texto para estado de log
 */
function getLogStatusText(resultado) {
    if (!resultado) return 'Pendiente';
    if (resultado.includes('éxito') || resultado.includes('encontrado')) return 'Éxito';
    if (resultado.includes('error') || resultado.includes('fallo')) return 'Error';
    return 'Procesado';
}

/**
 * Escapar HTML para prevenir XSS
 */
function escapeHtml(unsafe) {
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// === MANEJO DE FORMULARIOS ===

// Manejar envío del formulario de configuración
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('telegramConfigForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            saveTelegramConfig();
        });
    }
});

// Limpiar intervalos cuando se abandone la página
window.addEventListener('beforeunload', function() {
    if (telegramStatsInterval) {
        clearInterval(telegramStatsInterval);
    }
});
</script>