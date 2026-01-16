jQuery(document).ready(function($) {
    // Add nonce for AJAX requests
    const nukeCacheNonce = nukeCacheData.nonce;

    // Cache deletion
    var isDeletingCache = false;
    var cacheOffset = 0;
    var totalFolders = 0;
    var totalProcessedFolders = 0;
    var totalSkippedFolders = 0;

    // Check if there's a cache deletion in progress
    var savedCacheProgress = localStorage.getItem('nuke_cache_progress');
    if (savedCacheProgress) {
        var progress = JSON.parse(savedCacheProgress);
        if (!progress.is_complete) {
            if (confirm('There is a cache deletion process in progress. Would you like to resume?')) {
                cacheOffset = progress.next_offset;
                totalFolders = progress.total;
                totalProcessedFolders = progress.processed;
                totalSkippedFolders = progress.skipped;
                startCacheDeletion();
            } else {
                localStorage.removeItem('nuke_cache_progress');
            }
        }
    }

    $('.nuke-cache-button.danger').on('click', function() {
        if (!isDeletingCache) {
            startCacheDeletion();
        }
    });

    function startCacheDeletion() {
        isDeletingCache = true;
        var $button = $('.nuke-cache-button.danger');
        var $progressContainer = $('.nuke-cache-progress');
        var $progressBar = $progressContainer.find('.nuke-cache-progress-bar');
        var $progressText = $('.nuke-cache-progress-text');
        
        $button.prop('disabled', true).text('Deleting Cache...');
        $progressContainer.show();
        
        if (!savedCacheProgress) {
            cacheOffset = 0;
            totalProcessedFolders = 0;
            totalSkippedFolders = 0;
        }
        
        $(window).on('beforeunload', function() {
            if (isDeletingCache) {
                return 'Cache deletion is in progress. Are you sure you want to leave?';
            }
        });
        
        processCacheBatch(cacheOffset, totalProcessedFolders, totalSkippedFolders);
    }

    function processCacheBatch(offset, processed, skipped) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'delete_cache',
                offset: offset,
                nonce: nukeCacheNonce
            },
            success: function(response) {
                if (response.success) {
                    processed += response.data.processed;
                    skipped += response.data.skipped;
                    totalFolders = response.data.total;
                    var progress = ((processed + skipped) / totalFolders) * 100;
                    $('.nuke-cache-progress-bar').css('width', progress + '%');
                    $('.nuke-cache-progress-text').text('Processing: ' + (processed + skipped) + '/' + totalFolders + ' (Skipped: ' + skipped + ')');
                    
                    localStorage.setItem('nuke_cache_progress', JSON.stringify({
                        processed: processed,
                        skipped: skipped,
                        total: totalFolders,
                        next_offset: response.data.next_offset,
                        is_complete: response.data.is_complete
                    }));
                    
                    if (!response.data.is_complete) {
                        processCacheBatch(response.data.next_offset, processed, skipped);
                    } else {
                        completeCacheDeletion();
                    }
                }
            },
            error: function() {
                alert('An error occurred while deleting cache. The process will resume from where it left off when you refresh the page.');
            }
        });
    }

    function completeCacheDeletion() {
        isDeletingCache = false;
        localStorage.removeItem('nuke_cache_progress');
        $('.nuke-cache-button.danger').prop('disabled', false).text('Delete Cache');
        setTimeout(function() {
            location.reload();
        }, 1000);
    }

    // Add cancel button for cache deletion
    if (!$('#nuke-cache-cancel').length) {
        $('.nuke-cache-button.danger').after(' <button id="nuke-cache-cancel" class="nuke-cache-button" style="display: none;">Cancel</button>');
    }

    $('#nuke-cache-cancel').on('click', function() {
        if (confirm('Are you sure you want to cancel the cache deletion process?')) {
            isDeletingCache = false;
            localStorage.removeItem('nuke_cache_progress');
            $('.nuke-cache-button.danger').prop('disabled', false).text('Delete Cache');
            $('#nuke-cache-cancel').hide();
            $('.nuke-cache-progress').hide();
        }
    });
}); 