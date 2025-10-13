/**
 * JavaScript para el shortcode de WhatsApp Condo360
 * Maneja la actualización automática del estado y QR
 */

(function($) {
    'use strict';

    let refreshInterval = null;
    let isRefreshing = false;

    /**
     * Inicialización cuando el documento está listo
     */
    $(document).ready(function() {
        initializeWhatsAppWidget();
    });

    /**
     * Inicializa el widget de WhatsApp
     */
    function initializeWhatsAppWidget() {
        const container = $('.condo360ws-container');
        
        if (container.length === 0) {
            return;
        }

        const autoRefresh = container.data('auto-refresh') === true;
        const refreshIntervalMs = parseInt(container.data('refresh-interval')) || 10000;

        // Configurar botón de actualización manual
        $('#condo360ws-refresh-btn').on('click', function() {
            refreshStatus();
        });

        // Actualización inicial
        refreshStatus();

        // Configurar actualización automática si está habilitada
        if (autoRefresh) {
            startAutoRefresh(refreshIntervalMs);
        }

        // Detener actualización automática cuando la página no está visible
        $(document).on('visibilitychange', function() {
            if (document.hidden) {
                stopAutoRefresh();
            } else if (autoRefresh) {
                startAutoRefresh(refreshIntervalMs);
            }
        });
    }

    /**
     * Actualiza el estado de WhatsApp
     */
    function refreshStatus() {
        if (isRefreshing) {
            return;
        }

        isRefreshing = true;
        updateLastRefreshTime();
        setLoadingState(true);

        // Obtener estado actual
        $.ajax({
            url: condo360ws_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'condo360ws_get_status',
                nonce: condo360ws_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    handleStatusUpdate(response.data);
                } else {
                    handleError(response.data.message || condo360ws_ajax.strings.error_loading);
                }
            },
            error: function(xhr, status, error) {
                handleError(condo360ws_ajax.strings.error_loading + ': ' + error);
            },
            complete: function() {
                isRefreshing = false;
                setLoadingState(false);
            }
        });
    }

    /**
     * Maneja la actualización del estado
     */
    function handleStatusUpdate(status) {
        const container = $('.condo360ws-container');
        const statusIndicator = $('#condo360ws-status');
        const statusDot = statusIndicator.find('.status-dot');
        const statusText = statusIndicator.find('.status-text');
        const qrContainer = $('#condo360ws-qr-container');
        const connectedContainer = $('#condo360ws-connected');
        const errorContainer = $('#condo360ws-error');

        // Limpiar estados anteriores
        qrContainer.hide();
        connectedContainer.hide();
        errorContainer.hide();

        if (status.connected) {
            // WhatsApp está conectado
            statusDot.removeClass('checking disconnected').addClass('connected');
            statusText.text(condo360ws_ajax.strings.connected);
            connectedContainer.show().addClass('fade-in');
            
            // Detener actualización automática cuando está conectado
            stopAutoRefresh();
            
        } else if (status.qrGenerated) {
            // Hay un QR disponible
            statusDot.removeClass('connected disconnected').addClass('checking');
            statusText.text(condo360ws_ajax.strings.scan_qr);
            loadQRCode();
            
        } else {
            // No hay conexión ni QR
            statusDot.removeClass('connected checking').addClass('disconnected');
            statusText.text(condo360ws_ajax.strings.disconnected);
            errorContainer.show().addClass('fade-in');
        }
    }

    /**
     * Carga el código QR
     */
    function loadQRCode() {
        $.ajax({
            url: condo360ws_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'condo360ws_get_qr',
                nonce: condo360ws_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data.qr) {
                    const qrContainer = $('#condo360ws-qr-container');
                    const qrImage = $('#condo360ws-qr-image');
                    
                    qrImage.attr('src', response.data.qr);
                    qrContainer.show().addClass('fade-in');
                    
                    // Verificar si el QR ha expirado
                    if (response.data.expiresAt) {
                        const expiresAt = new Date(response.data.expiresAt);
                        const now = new Date();
                        const timeUntilExpiry = expiresAt.getTime() - now.getTime();
                        
                        if (timeUntilExpiry > 0) {
                            setTimeout(function() {
                                if (!$('#condo360ws-connected').is(':visible')) {
                                    handleQRExpired();
                                }
                            }, timeUntilExpiry);
                        }
                    }
                } else {
                    handleError(response.data.message || 'Error obteniendo código QR');
                }
            },
            error: function(xhr, status, error) {
                handleError('Error cargando código QR: ' + error);
            }
        });
    }

    /**
     * Maneja cuando el QR ha expirado
     */
    function handleQRExpired() {
        const qrContainer = $('#condo360ws-qr-container');
        const errorContainer = $('#condo360ws-error');
        
        qrContainer.hide();
        errorContainer.show().addClass('fade-in qr-expired');
        
        $('#condo360ws-error-message').text(condo360ws_ajax.strings.qr_expired);
        
        // Intentar obtener un nuevo QR después de un breve delay
        setTimeout(function() {
            refreshStatus();
        }, 2000);
    }

    /**
     * Maneja errores
     */
    function handleError(message) {
        const errorContainer = $('#condo360ws-error');
        const statusIndicator = $('#condo360ws-status');
        const statusDot = statusIndicator.find('.status-dot');
        const statusText = statusIndicator.find('.status-text');
        
        statusDot.removeClass('connected checking').addClass('disconnected');
        statusText.text(condo360ws_ajax.strings.disconnected);
        
        $('#condo360ws-error-message').text(message);
        errorContainer.show().addClass('fade-in');
        
        // Ocultar otros contenedores
        $('#condo360ws-qr-container').hide();
        $('#condo360ws-connected').hide();
    }

    /**
     * Establece el estado de carga
     */
    function setLoadingState(loading) {
        const container = $('.condo360ws-container');
        const refreshBtn = $('#condo360ws-refresh-btn');
        
        if (loading) {
            container.addClass('condo360ws-loading');
            refreshBtn.prop('disabled', true).text('Actualizando...');
        } else {
            container.removeClass('condo360ws-loading');
            refreshBtn.prop('disabled', false).text('Actualizar Estado');
        }
    }

    /**
     * Actualiza el tiempo de última actualización
     */
    function updateLastRefreshTime() {
        const now = new Date();
        const timeString = now.toLocaleTimeString();
        $('#condo360ws-last-update').text(timeString);
    }

    /**
     * Inicia la actualización automática
     */
    function startAutoRefresh(interval) {
        stopAutoRefresh(); // Limpiar interval anterior si existe
        
        refreshInterval = setInterval(function() {
            // Solo actualizar si no está conectado
            if (!$('#condo360ws-connected').is(':visible')) {
                refreshStatus();
            }
        }, interval);
    }

    /**
     * Detiene la actualización automática
     */
    function stopAutoRefresh() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
            refreshInterval = null;
        }
    }

    /**
     * Función para enviar mensaje (para uso futuro)
     */
    function sendMessage(message) {
        return $.ajax({
            url: condo360ws_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'condo360ws_send_message',
                nonce: condo360ws_ajax.nonce,
                message: message
            }
        });
    }

    // Exponer funciones globalmente para uso externo
    window.condo360ws = {
        refreshStatus: refreshStatus,
        sendMessage: sendMessage,
        startAutoRefresh: startAutoRefresh,
        stopAutoRefresh: stopAutoRefresh
    };

})(jQuery);
