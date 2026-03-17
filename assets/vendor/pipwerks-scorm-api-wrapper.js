/*
pipwerks SCORM Wrapper for JavaScript
pipwerks.com/code/scorm/
Copyright (c) Philip Hutchison, 2008-2023
MIT License — https://opensource.org/licenses/MIT

Adapted for SCORM 1.2 only (simplified).
*/

var pipwerks = {};
pipwerks.UTILS = {};
pipwerks.debug = { isActive: false };

pipwerks.SCORM = {
    version: "1.2",
    handleCompletionStatus: true,
    handleExitMode: true,
    API: { handle: null, isFound: false },
    connection: { isActive: false },
    data: {
        completionStatus: null,
        exitStatus: null
    },
    debug: {}
};

/* ---- API Discovery ---- */
pipwerks.SCORM.API.find = function (win) {
    var API = null,
        findAttempts = 0,
        findAttemptLimit = 500,
        traceMsgPrefix = "SCORM.API.find";

    while (!win.API && win.parent !== win && findAttempts <= findAttemptLimit) {
        findAttempts++;
        win = win.parent;
    }
    API = win.API || null;
    if (!API) {
        pipwerks.UTILS.trace(traceMsgPrefix + ": API not found in parent chain");
    }
    return API;
};

pipwerks.SCORM.API.get = function () {
    var API = null,
        win = window;

    if (win.parent && win.parent !== win) {
        API = pipwerks.SCORM.API.find(win.parent);
    }
    if (!API && win.top) {
        API = pipwerks.SCORM.API.find(win.top);
    }
    if (!API && win.opener) {
        API = pipwerks.SCORM.API.find(win.opener);
    }
    if (!API) {
        // Last resort: check direct window
        API = win.API || null;
    }

    if (API) {
        pipwerks.SCORM.API.handle = API;
        pipwerks.SCORM.API.isFound = true;
    } else {
        pipwerks.UTILS.trace("SCORM.API.get: API not found");
    }
    return API;
};

pipwerks.SCORM.API.getHandle = function () {
    var API = pipwerks.SCORM.API.handle;
    if (!API && !pipwerks.SCORM.API.isFound) {
        API = pipwerks.SCORM.API.get();
        if (!API && !pipwerks.SCORM.API.isFound) {
            pipwerks.UTILS.trace("SCORM.API.getHandle: API not found");
        }
    }
    return API;
};

/* ---- Connection ---- */
pipwerks.SCORM.connection.initialize = function () {
    var success = false,
        API = pipwerks.SCORM.API.getHandle(),
        errorMsg;

    if (API) {
        switch (pipwerks.SCORM.version) {
            case "1.2":
                success = Boolean(API.LMSInitialize(""));
                break;
            case "2004":
                success = Boolean(API.Initialize(""));
                break;
        }
        if (success) {
            pipwerks.SCORM.connection.isActive = true;
            if (pipwerks.SCORM.handleCompletionStatus) {
                var status = pipwerks.SCORM.status("get");
                if (status === "not attempted") {
                    pipwerks.SCORM.status("set", "incomplete");
                }
                pipwerks.SCORM.data.completionStatus = status;
            }
        } else {
            errorMsg = "SCORM.connection.initialize failed";
            pipwerks.UTILS.trace(errorMsg);
        }
    } else {
        pipwerks.UTILS.trace("SCORM.connection.initialize: no API found");
    }
    return success;
};

pipwerks.SCORM.connection.terminate = function () {
    var success = false,
        API = pipwerks.SCORM.API.getHandle();

    if (API && pipwerks.SCORM.connection.isActive) {
        if (pipwerks.SCORM.handleExitMode && !pipwerks.SCORM.data.completionStatus) {
            if (pipwerks.SCORM.data.completionStatus !== "passed" &&
                pipwerks.SCORM.data.completionStatus !== "failed") {
                pipwerks.SCORM.status("set", "incomplete");
            }
        }
        switch (pipwerks.SCORM.version) {
            case "1.2":
                success = Boolean(API.LMSFinish(""));
                break;
            case "2004":
                success = Boolean(API.Terminate(""));
                break;
        }
        if (success) {
            pipwerks.SCORM.connection.isActive = false;
        }
    }
    return success;
};

/* ---- Data ---- */
pipwerks.SCORM.data.get = function (parameter) {
    var value = "",
        API = pipwerks.SCORM.API.getHandle();

    if (API && pipwerks.SCORM.connection.isActive) {
        switch (pipwerks.SCORM.version) {
            case "1.2":
                value = API.LMSGetValue(parameter);
                break;
            case "2004":
                value = API.GetValue(parameter);
                break;
        }
    }
    return String(value);
};

pipwerks.SCORM.data.set = function (parameter, value) {
    var success = false,
        API = pipwerks.SCORM.API.getHandle();

    if (API && pipwerks.SCORM.connection.isActive) {
        switch (pipwerks.SCORM.version) {
            case "1.2":
                success = Boolean(API.LMSSetValue(parameter, value));
                break;
            case "2004":
                success = Boolean(API.SetValue(parameter, value));
                break;
        }
    }
    return success;
};

pipwerks.SCORM.data.save = function () {
    var success = false,
        API = pipwerks.SCORM.API.getHandle();

    if (API && pipwerks.SCORM.connection.isActive) {
        switch (pipwerks.SCORM.version) {
            case "1.2":
                success = Boolean(API.LMSCommit(""));
                break;
            case "2004":
                success = Boolean(API.Commit(""));
                break;
        }
    }
    return success;
};

/* ---- Status shortcut ---- */
pipwerks.SCORM.status = function (action, status) {
    var parameter = (pipwerks.SCORM.version === "1.2")
        ? "cmi.core.lesson_status"
        : "cmi.completion_status";

    if (action === "get") {
        return pipwerks.SCORM.data.get(parameter);
    } else if (action === "set") {
        return pipwerks.SCORM.data.set(parameter, status);
    }
    return false;
};

/* ---- Public Aliases ---- */
var scorm = {
    init:     function () { return pipwerks.SCORM.connection.initialize(); },
    quit:     function () { return pipwerks.SCORM.connection.terminate(); },
    get:      function (p) { return pipwerks.SCORM.data.get(p); },
    set:      function (p, v) { return pipwerks.SCORM.data.set(p, v); },
    save:     function () { return pipwerks.SCORM.data.save(); },
    status:   function (a, s) { return pipwerks.SCORM.status(a, s); },
    isActive: function () { return pipwerks.SCORM.connection.isActive; }
};

/* ---- Utils ---- */
pipwerks.UTILS.trace = function (msg) {
    if (pipwerks.debug.isActive && window.console) {
        console.log("[SCORM] " + msg);
    }
};
