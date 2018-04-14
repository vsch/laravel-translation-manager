import { boxedState_$ } from "./CreateAppStore";
import { isArray, isObject, isString } from "./helpers";

export function absoluteUrlPrefix() {
    return window.axios.defaults.baseURL;
}

/**
 * Combine path parts and query params into the API URL
 *
 * @param args          arrays and strings are path parts spliced with '/', objects are query param name and value
 * @returns {string}    adds global xDebugSession to end of query params
 */
export function apiURL(...args) {
    let xDebugSession = boxedState_$.globalSettings.appSettings.xDebugSession();
    let path = "";
    let querySep = "?";
    let query = "";

    function addPathPart(part) {
        if (part && isString(part)) {
            if (path && !path.endsWith('/') && !part.startsWith('/')) path += '/';
            path += part;
        } else if (isArray(part)) {
            part.forEach(part => {
                addPathPart(part);
            });
        } else if (isObject(part)) {
            for (let key in part) {
                if (!part.hasOwnProperty(key)) continue;
                const param = part[key];

                if (param && !isObject(param) && !isArray(param)) {
                    query += querySep;
                    querySep = "&";
                    query += encodeURIComponent(key) + "=" + encodeURIComponent(param);
                }
            }
        }
    }

    let iMax = args.length;
    for (let i = 0; i < iMax; i++) {
        let arg = args[i];
        addPathPart(arg);
    }

    if (xDebugSession) {
        query += querySep;
        querySep = '&';
        path += xDebugSession;
    }
    return path + query;
}

// Route::post('add/{group}', '\\Vsch\\TranslationManager\\Controller@postAddSuffixedKeys');
// Route::post('delete_keys/{group}', '\\Vsch\\TranslationManager\\Controller@postDeleteKeys');
// Route::post('delete_suffixed_keys/{group?}', '\\Vsch\\TranslationManager\\Controller@postDeleteSuffixedKeys');
// Route::post('copy_keys/{group}', '\\Vsch\\TranslationManager\\Controller@postCopyKeys');
// Route::post('find', '\\Vsch\\TranslationManager\\Controller@postFind');
// Route::post('move_keys/{group}', '\\Vsch\\TranslationManager\\Controller@postMoveKeys');

export const URL_GET_TRANSLATIONS = (ns, lng) => {
    return {
        url: apiURL('api/translations', ns, lng),
        type: 'GET',
        data: undefined,
    };
};

export const URL_GET_MISMATCHES = (connectionName, primaryLocale, translatingLocale) => {
    return {
        url: apiURL('api/mismatches'),
        type: 'POST',
        data: JSON.stringify({
            connectionName: connectionName,
            primaryLocale: primaryLocale,
            translatingLocale: translatingLocale,
        }),
    };
};

export const URL_GET_SEARCH = (connectionName, displayLocales, searchText) => {
    return {
        url: apiURL('api/search'),
        type: 'POST',
        data: JSON.stringify({
            connectionName: connectionName,
            displayLocales: displayLocales,
            searchText: searchText,
        }),
    };
};

export const URL_GET_TRANSLATION_TABLE = (group, connectionName, primaryLocale, translatingLocale, displayLocales) => {
    return {
        url: apiURL('api/translation-table', group),
        type: 'POST',
        data: JSON.stringify({
            connectionName: connectionName,
            primaryLocale: primaryLocale,
            translatingLocale: translatingLocale,
            displayLocales: displayLocales,
        }),
    };
};

export const URL_GET_SUMMARY_DATA = (connectionName, displayLocales) => {
    return {
        url: apiURL('api/summary'),
        type: 'POST',
        data: JSON.stringify({
            connectionName: connectionName,
            displayLocales: displayLocales,
        }),
    };
};

export const URL_GET_USER_LIST = (connectionName, displayLocales) => {
    return {
        url: apiURL('api/user-list'),
        type: 'POST',
        data: JSON.stringify({
            connectionName: connectionName,
            displayLocales: displayLocales,
        }),
    };
};

export const URL_CLEAR_USER_UI_SETTINGS = (userId, connectionName) => {
    return {
        url: apiURL('api/clear-ui-settings'),
        type: 'POST',
        data: JSON.stringify({
            connectionName: connectionName,
            userId: userId,
        }),
    };
};

export const URL_ADD_SUFFIXED_KEYS = (group, keys, suffixes, connectionName) => {
    return {
        url: apiURL('api/add-suffixed-keys', group),
        type: 'POST',
        data: JSON.stringify({
            connectionName: connectionName,
            keys: keys,
            suffixes: suffixes,
        }),
    };
};

export const URL_DELETE_SUFFIXED_KEYS = (group, keys, suffixes, connectionName) => {
    return {
        url: apiURL('api/delete-suffixed-keys', group),
        type: 'POST',
        data: JSON.stringify({
            connectionName: connectionName,
            keys: keys,
            suffixes: suffixes,
        }),
    };
};

export const URL_DELETE_GROUP = (group, connectionName) => {
    return {
        url: apiURL('api/delete-group', group),
        type: 'POST',
        data: JSON.stringify({
            connectionName: connectionName,
        }),
    };
};

export const URL_IMPORT_GROUP = (group, replace, connectionName) => {
    return {
        url: apiURL('api/import-group', group),
        type: 'POST',
        data: JSON.stringify({
            connectionName: connectionName,
            replace: replace,
        }),
    };
};

export const URL_PUBLISH_GROUP = (group, connectionName) => {
    return {
        url: apiURL('api/publish-group', group),
        type: 'POST',
        data: JSON.stringify({
            connectionName: connectionName,
        }),
    };
};

export const URL_ZIP_TRANSLATIONS = (group, connectionName) => {
    return {
        url: apiURL('api/zipped-translations', group),
        type: 'GET',
    };
};

export const URL_FIND_REFERENCES = (connectionName) => {
    return {
        url: apiURL('api/find-references'),
        type: 'POST',
        data: JSON.stringify({
            connectionName: connectionName,
        }),
    };
};

export const URL_GET_APP_SETTINGS = () => {
    return {
        url: apiURL('api/app-settings'),
        type: 'GET',
    };
};

export const URL_POST_APP_SETTINGS = (appSettings) => {
    return {
        url: apiURL('api/app-settings'),
        type: 'POST',
        data: JSON.stringify(appSettings),
    };
};

// will use default connection and posted key if available, else url key
export const URL_SHOW_KEY_REFERENCES = (group, key, connectionName) => {
    return {
        url: apiURL('api/key-references', group, connectionName ? undefined : key),
        type: 'POST',
        data: JSON.stringify({
            connectionName: connectionName || '',
            key: key,
        }),
    };
};

export const POST_MISSING_KEYS = "api/missing-keys";
export const URL_POST_MISSING_KEYS = (missingKeys) => {
    return {
        url: apiURL('api/missing-keys'),
        type: 'POST',
        data: JSON.stringify({
            missingKeys: missingKeys,
        }),
    };
};

// TODO: convert these to function calls that take required arguments to complete the URL 
export const POST_DELETE_TRANSLATION = "api/delete";
export const POST_UNDELETE_TRANSLATION = "api/undelete";
export const POST_EDIT_TRANSLATION = 'api/edit';
export const POST_TRANS_FILTER = 'api/trans-filters';
export const POST_USER_LOCALES = "api/user_locales";
export const RENAME_GROUP_URL = 'api/rename-group'; // not implemented
