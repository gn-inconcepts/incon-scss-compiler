jQuery(document).ready(function($) {
    'use strict';
    
    var compiling = false;
    var watchInterval = null;
    
    $('#compile-all').on('click', function() {
        if (compiling) return;
        
        compiling = true;
        var $btn = $(this);
        $btn.prop('disabled', true).text(inconScss.strings.compiling);
        
        $('.compilation-progress').show();
        $('.compilation-results').hide().find('.results-list').empty();
        
        console.log('Starting compilation...');
        console.log('AJAX URL:', inconScss.ajaxUrl);
        console.log('Nonce:', inconScss.nonce);
        
        $.ajax({
            url: inconScss.ajaxUrl,
            type: 'POST',
            data: {
                action: 'incon_scss_compile',
                nonce: inconScss.nonce
            },
            success: function(response) {
                console.log('Response:', response);
                if (response.success) {
                    if (response.files) {
                        displayResults(response.files);
                    }
                    showNotification(response.message || 'Compilation completed', 'success');
                } else {
                    var errorMsg = response.data && response.data.message ? response.data.message : 'Compilation failed';
                    showNotification(errorMsg, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                console.error('Response:', xhr.responseText);
                showNotification('Compilation failed: ' + error, 'error');
            },
            complete: function() {
                compiling = false;
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Compile All Files');
                $('.compilation-progress').hide();
            }
        });
    });
    
    $(document).on('click', '.compile-single', function() {
        var $btn = $(this);
        var file = $btn.data('file');
        
        console.log('Compiling single file:', file);
        
        $btn.prop('disabled', true).text('Compiling...');
        
        $.ajax({
            url: inconScss.ajaxUrl,
            type: 'POST',
            data: {
                action: 'incon_scss_compile',
                nonce: inconScss.nonce,
                file: file
            },
            success: function(response) {
                console.log('Single file response:', response);
                if (response.success) {
                    showNotification('File compiled successfully', 'success');
                    updateFileRow(file, response);
                } else {
                    var errorMsg = response.data && response.data.message ? response.data.message : 'Compilation error';
                    showNotification(errorMsg, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Single compile error:', status, error);
                showNotification('Compilation error: ' + error, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Compile');
            }
        });
    });
    
    $('#clear-cache').on('click', function() {
        if (!confirm('Are you sure you want to clear the cache?')) return;
        
        var $btn = $(this);
        $btn.prop('disabled', true);
        
        $.ajax({
            url: inconScss.ajaxUrl,
            type: 'POST',
            data: {
                action: 'incon_scss_clear_cache',
                nonce: inconScss.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification(response.data.message, 'success');
                }
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });
    
    $('#toggle-watch').on('click', function() {
        var $btn = $(this);
        var watching = $btn.data('watching') === 'true';
        
        if (watching) {
            clearInterval(watchInterval);
            $btn.data('watching', 'false')
                .html('<span class="dashicons dashicons-visibility"></span> Start Watching')
                .removeClass('button-primary');
        } else {
            startWatching();
            $btn.data('watching', 'true')
                .html('<span class="dashicons dashicons-no-alt"></span> Stop Watching')
                .addClass('button-primary');
        }
    });
    
    $('#apply-bulk-action').on('click', function() {
        var action = $('#bulk-action').val();
        if (!action) return;
        
        var selected = [];
        $('.file-checkbox:checked').each(function() {
            selected.push($(this).val());
        });
        
        if (selected.length === 0) {
            showNotification('No files selected', 'warning');
            return;
        }
        
        switch (action) {
            case 'compile':
                compileBulk(selected);
                break;
            case 'delete-css':
                if (confirm('Are you sure you want to delete CSS output for selected files?')) {
                    deleteCssFiles(selected);
                }
                break;
        }
    });
    
    $('#select-all-files').on('change', function() {
        $('.file-checkbox').prop('checked', $(this).is(':checked'));
    });
    
    $('#file-search').on('keyup', function() {
        var search = $(this).val().toLowerCase();
        
        $('tbody tr').each(function() {
            var $row = $(this);
            var filename = $row.find('td:nth-child(2) strong').text().toLowerCase();
            
            if (filename.indexOf(search) > -1) {
                $row.show();
            } else {
                $row.hide();
            }
        });
    });
    
    $('#file-filter').on('change', function() {
        var filter = $(this).val();
        
        $('tbody tr').each(function() {
            var $row = $(this);
            
            switch (filter) {
                case 'main':
                    var filename = $row.find('td:nth-child(2) strong').text();
                    if (filename.charAt(0) === '_') {
                        $row.hide();
                    } else {
                        $row.show();
                    }
                    break;
                case 'modified':
                    $row.show();
                    break;
                default:
                    $row.show();
            }
        });
    });
    
    $('.edit-file').on('click', function(e) {
        e.preventDefault();
        var file = $(this).data('file');
        openFileEditor(file);
    });
    
    $('.view-deps').on('click', function(e) {
        e.preventDefault();
        var file = $(this).data('file');
        showDependencies(file);
    });
    
    $('.modal-close').on('click', function() {
        $(this).closest('.incon-scss-modal').fadeOut();
    });
    
    $('#quick-settings').on('submit', function(e) {
        e.preventDefault();
        
        var data = $(this).serialize();
        data += '&action=incon_scss_save_quick_settings&nonce=' + inconScss.nonce;
        
        $.post(inconScss.ajaxUrl, data, function(response) {
            if (response.success) {
                showNotification('Settings saved', 'success');
            }
        });
    });
    
    function displayResults(files) {
        var $results = $('.compilation-results');
        var $list = $results.find('.results-list');
        
        files.forEach(function(file) {
            var status = file.success ? 'success' : 'error';
            var icon = file.success ? 'yes-alt' : 'warning';
            var message = file.success 
                ? file.file + ' → ' + file.output + ' (' + file.size + ', ' + file.time + ')'
                : file.file + ': ' + file.message;
            
            $list.append(
                '<div class="result-item result-' + status + '">' +
                '<span class="dashicons dashicons-' + icon + '"></span> ' +
                message +
                '</div>'
            );
        });
        
        $results.show();
    }
    
    function updateFileRow(file, response) {
        var $row = $('tr[data-file="' + file + '"]');
        if (!$row.length) return;
        
        if (response.success) {
            var outputHtml = '<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> ' + response.size;
            $row.find('td:nth-child(5)').html(outputHtml);
        }
    }
    
    function showNotification(message, type) {
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wrap h1').after($notice);
        
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    function compileBulk(files) {
        var compiled = 0;
        var total = files.length;
        
        $('.compilation-progress').show();
        updateProgress(0, total);
        
        function compileNext() {
            if (compiled >= total) {
                $('.compilation-progress').hide();
                showNotification('Bulk compilation complete', 'success');
                return;
            }
            
            var file = files[compiled];
            
            $.ajax({
                url: inconScss.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'incon_scss_compile',
                    nonce: inconScss.nonce,
                    file: file
                },
                success: function(response) {
                    updateFileRow(file, response);
                },
                complete: function() {
                    compiled++;
                    updateProgress(compiled, total);
                    compileNext();
                }
            });
        }
        
        compileNext();
    }
    
    function updateProgress(current, total) {
        var percent = (current / total) * 100;
        $('.progress-fill').css('width', percent + '%');
        $('.progress-text').text('Compiling: ' + current + ' / ' + total);
    }
    
    function startWatching() {
        watchInterval = setInterval(function() {
            $.ajax({
                url: inconScss.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'incon_scss_check_changes',
                    nonce: inconScss.nonce
                },
                success: function(response) {
                    if (response.changed) {
                        $('#compile-all').trigger('click');
                    }
                }
            });
        }, inconScss.settings.watch_interval || 1000);
    }
    
    function openFileEditor(file) {
        $('#file-editor-modal').fadeIn();
        
        if (window.wp && wp.codeEditor) {
            var editorSettings = wp.codeEditor.defaultSettings ? _.clone(wp.codeEditor.defaultSettings) : {};
            editorSettings.codemirror = _.extend({}, editorSettings.codemirror, {
                mode: 'css',
                lineNumbers: true,
                lineWrapping: true,
                theme: 'default'
            });
            
            var editor = wp.codeEditor.initialize($('#scss-editor'), editorSettings);
            
            $.ajax({
                url: inconScss.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'incon_scss_get_file_content',
                    nonce: inconScss.nonce,
                    file: file
                },
                success: function(response) {
                    if (response.success) {
                        editor.codemirror.setValue(response.content);
                    }
                }
            });
            
            $('#save-file').off('click').on('click', function() {
                var content = editor.codemirror.getValue();
                
                $.ajax({
                    url: inconScss.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'incon_scss_save_file',
                        nonce: inconScss.nonce,
                        file: file,
                        content: content
                    },
                    success: function(response) {
                        if (response.success) {
                            showNotification('File saved successfully', 'success');
                            $('#file-editor-modal').fadeOut();
                        }
                    }
                });
            });
        }
    }
    
    function showDependencies(file) {
        $('#dependencies-modal').fadeIn();
        
        $.ajax({
            url: inconScss.ajaxUrl,
            type: 'POST',
            data: {
                action: 'incon_scss_get_dependencies',
                nonce: inconScss.nonce,
                file: file
            },
            success: function(response) {
                if (response.success && response.data) {
                    drawDependencyGraph(response.data);
                }
            }
        });
    }
    
    function drawDependencyGraph(data) {
        $('#dependency-graph').html('<canvas id="dep-canvas"></canvas>');
    }
    
    function deleteCssFiles(files) {
        $.ajax({
            url: inconScss.ajaxUrl,
            type: 'POST',
            data: {
                action: 'incon_scss_delete_css',
                nonce: inconScss.nonce,
                files: files
            },
            success: function(response) {
                if (response.success) {
                    showNotification('CSS files deleted', 'success');
                    location.reload();
                }
            }
        });
    }
});