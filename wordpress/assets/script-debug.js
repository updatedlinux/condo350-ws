/**
 * JavaScript simplificado para depuración del shortcode de WhatsApp Condo360
 */

console.log('Condo360 WhatsApp: Script cargado');

jQuery(document).ready(function($) {
    console.log('Condo360 WhatsApp: jQuery listo');
    
    // Verificar que el container existe
    const container = $('.condo360ws-container');
    console.log('Condo360 WhatsApp: Container encontrado:', container.length);
    
    if (container.length === 0) {
        console.log('Condo360 WhatsApp: No se encontró el container');
        return;
    }
    
    // Verificar variables AJAX
    console.log('Condo360 WhatsApp: Variables AJAX:', typeof condo360ws_ajax);
    if (typeof condo360ws_ajax !== 'undefined') {
        console.log('Condo360 WhatsApp: AJAX URL:', condo360ws_ajax.ajax_url);
        console.log('Condo360 WhatsApp: Nonce:', condo360ws_ajax.nonce);
    }
    
    // Función simplificada para obtener estado
    function getStatus() {
        console.log('Condo360 WhatsApp: Obteniendo estado...');
        
        if (typeof condo360ws_ajax === 'undefined') {
            console.error('Condo360 WhatsApp: Variables AJAX no disponibles');
            $('#condo360ws-status .status-text').text('Error: Variables AJAX no disponibles');
            return;
        }
        
        $.ajax({
            url: condo360ws_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'condo360ws_get_status',
                nonce: condo360ws_ajax.nonce
            },
            success: function(response) {
                console.log('Condo360 WhatsApp: Respuesta recibida:', response);
                
                if (response.success && response.data) {
                    const status = response.data;
                    console.log('Condo360 WhatsApp: Estado procesado:', status);
                    
                    const statusDot = $('#condo360ws-status .status-dot');
                    const statusText = $('#condo360ws-status .status-text');
                    const qrContainer = $('#condo360ws-qr-container');
                    const connectedContainer = $('#condo360ws-connected');
                    const errorContainer = $('#condo360ws-error');
                    
                    // Limpiar estados anteriores
                    qrContainer.hide();
                    connectedContainer.hide();
                    errorContainer.hide();
                    
                    if (status.connected) {
                        console.log('Condo360 WhatsApp: Mostrando estado conectado');
                        statusDot.removeClass('checking disconnected').addClass('connected');
                        statusText.text('WhatsApp Conectado');
                        connectedContainer.show();
                    } else if (status.qrGenerated) {
                        console.log('Condo360 WhatsApp: Mostrando QR');
                        statusDot.removeClass('connected disconnected').addClass('checking');
                        statusText.text('Escanea el código QR');
                        qrContainer.show();
                        
                        // Cargar QR
                        loadQR();
                    } else {
                        console.log('Condo360 WhatsApp: Mostrando error');
                        statusDot.removeClass('connected checking').addClass('disconnected');
                        statusText.text('WhatsApp Desconectado');
                        errorContainer.show();
                    }
                } else {
                    console.error('Condo360 WhatsApp: Respuesta no exitosa:', response);
                    $('#condo360ws-status .status-text').text('Error obteniendo estado');
                }
            },
            error: function(xhr, status, error) {
                console.error('Condo360 WhatsApp: Error AJAX:', xhr, status, error);
                $('#condo360ws-status .status-text').text('Error: ' + error);
            }
        });
    }
    
    // Función simplificada para cargar QR
    function loadQR() {
        console.log('Condo360 WhatsApp: Cargando QR...');
        
        $.ajax({
            url: condo360ws_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'condo360ws_get_qr',
                nonce: condo360ws_ajax.nonce
            },
            success: function(response) {
                console.log('Condo360 WhatsApp: QR recibido:', response);
                
                if (response.success && response.data && response.data.qr) {
                    const qrImage = $('#condo360ws-qr-image');
                    const qrDataUrl = 'data:image/png;base64,' + response.data.qr;
                    qrImage.attr('src', qrDataUrl);
                    console.log('Condo360 WhatsApp: QR cargado en imagen');
                } else {
                    console.error('Condo360 WhatsApp: Error cargando QR:', response);
                }
            },
            error: function(xhr, status, error) {
                console.error('Condo360 WhatsApp: Error cargando QR:', xhr, status, error);
            }
        });
    }
    
    // Configurar botón de actualización
    $('#condo360ws-refresh-btn').on('click', function() {
        console.log('Condo360 WhatsApp: Botón actualizar clickeado');
        getStatus();
    });
    
    // Actualización inicial
    console.log('Condo360 WhatsApp: Iniciando actualización inicial...');
    getStatus();
    
    // Actualización automática cada 10 segundos
    setInterval(function() {
        console.log('Condo360 WhatsApp: Actualización automática...');
        getStatus();
    }, 10000);
    
    console.log('Condo360 WhatsApp: Inicialización completada');
});
