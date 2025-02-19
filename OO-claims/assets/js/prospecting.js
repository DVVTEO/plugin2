/**
 * Prospecting Management Scripts
 *
 * @package ClaimsManagement
 * @version 3.5
 * @author DVVTEO
 * @since 2025-02-19 00:54:05
 */

jQuery(document).ready(function($) {
    'use strict';
    
    var Prospecting = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('#prospects_csv').on('change', this.validateFileInput);
            $('.prospect-import-form').on('submit', this.validateFormSubmission);
            $('.notice-dismiss').on('click', this.dismissNotice);
            $('#doaction, #doaction2').on('click', this.confirmBulkAction);
            $('.change-status').on('click', this.confirmStatusChange);
        },

        validateFileInput: function() {
            var file = this.files[0];
            if (file) {
                if (file.type !== 'text/csv' && !file.name.endsWith('.csv')) {
                    alert(cmProspecting.i18n.invalidFileType);
                    this.value = '';
                }
            }
        },

        validateFormSubmission: function(e) {
            var fileInput = $('#prospects_csv')[0];
            if (!fileInput.files.length) {
                e.preventDefault();
                alert(cmProspecting.i18n.noFileSelected);
                return false;
            }
        },

        dismissNotice: function() {
            $(this).closest('.notice').fadeOut();
        },

        confirmBulkAction: function(e) {
            var action = $('#bulk-action-selector-top, #bulk-action-selector-bottom').val();
            if (action === 'delete') {
                if (!confirm(cmProspecting.i18n.confirmDelete)) {
                    e.preventDefault();
                }
            } else if (action === 'convert') {
                if (!confirm(cmProspecting.i18n.confirmConvert)) {
                    e.preventDefault();
                }
            }
        },

        confirmStatusChange: function(e) {
            if (!confirm(cmProspecting.i18n.confirmStatusChange)) {
                e.preventDefault();
            }
        }
    };

    // Initialize the module
    Prospecting.init();
});