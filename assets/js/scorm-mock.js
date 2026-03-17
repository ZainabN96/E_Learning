/**
 * SCORM 1.2 API Mock — development/standalone use only.
 * Simulates window.API with localStorage persistence.
 * Loaded when ?debug=1 is in the URL OR no real API is found.
 */
(function () {
    'use strict';

    const STORAGE_KEY = 'scorm_mock_data';

    const defaultData = {
        'cmi.core.lesson_status':   'not attempted',
        'cmi.core.lesson_location': '',
        'cmi.core.score.raw':       '',
        'cmi.core.score.min':       '0',
        'cmi.core.score.max':       '100',
        'cmi.core.session_time':    '00:00:00',
        'cmi.core.total_time':      '00:00:00',
        'cmi.core.student_id':      'dev_user_001',
        'cmi.core.student_name':    'Testbenutzer, Dev',
        'cmi.core.credit':          'credit',
        'cmi.core.entry':           'ab-initio',
        'cmi.suspend_data':         '',
        'cmi.launch_data':          '',
    };

    // Restore from localStorage
    let stored = {};
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (raw) stored = JSON.parse(raw);
    } catch (e) { /* ignore */ }

    const data = Object.assign({}, defaultData, stored);

    // Objectives and interactions stored dynamically
    const dynamicPrefixes = ['cmi.objectives', 'cmi.interactions'];

    function isDynamic(key) {
        return dynamicPrefixes.some(p => key.startsWith(p));
    }

    const log = (action, key, val) => {
        if (window.ScormDebugPanel) {
            window.ScormDebugPanel.log(action, key, val);
        }
    };

    window.API = {
        LMSInitialize: function (s) {
            log('INIT', '', '');
            return 'true';
        },
        LMSFinish: function (s) {
            log('FINISH', '', '');
            try { localStorage.setItem(STORAGE_KEY, JSON.stringify(data)); } catch (e) { /* ignore */ }
            return 'true';
        },
        LMSGetValue: function (key) {
            let val = '';
            if (isDynamic(key)) {
                val = data[key] !== undefined ? data[key] : '';
            } else {
                val = data[key] !== undefined ? data[key] : '';
            }
            log('GET', key, val);
            return String(val);
        },
        LMSSetValue: function (key, val) {
            data[key] = String(val);
            log('SET', key, val);
            return 'true';
        },
        LMSCommit: function (s) {
            try { localStorage.setItem(STORAGE_KEY, JSON.stringify(data)); } catch (e) { /* ignore */ }
            log('COMMIT', '', '');
            return 'true';
        },
        LMSGetLastError: function () { return '0'; },
        LMSGetErrorString: function (e) { return 'No error'; },
        LMSGetDiagnostic: function (e) { return 'No diagnostic info available'; },

        // Dev helper: reset all stored data
        _reset: function () {
            localStorage.removeItem(STORAGE_KEY);
            Object.keys(data).forEach(k => { if (!defaultData.hasOwnProperty(k)) delete data[k]; });
            Object.assign(data, defaultData);
            log('RESET', '', '');
        },
        _dump: function () { return JSON.parse(JSON.stringify(data)); }
    };

    console.log('%c[SCORM Mock] API loaded. window.API is available. Use window.API._dump() to inspect state.', 'color: #4caf50; font-weight: bold;');
})();
