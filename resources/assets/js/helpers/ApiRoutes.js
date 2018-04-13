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

// Route::post('add/{group}', '\\Vsch\\TranslationManager\\Controller@postAdd');
// Route::post('delete_keys/{group}', '\\Vsch\\TranslationManager\\Controller@postDeleteKeys');
// Route::post('delete_suffixed_keys/{group?}', '\\Vsch\\TranslationManager\\Controller@postDeleteSuffixedKeys');
// Route::post('copy_keys/{group}', '\\Vsch\\TranslationManager\\Controller@postCopyKeys');
// Route::post('find', '\\Vsch\\TranslationManager\\Controller@postFind');
// Route::post('move_keys/{group}', '\\Vsch\\TranslationManager\\Controller@postMoveKeys');

export const POST_ADD_SUFFIXED_KEYS = 'add';
export const POST_CLEAR_USER_UI_SETTINGS = 'clear-ui-settings';
export const POST_DELETE_SUFFIXED_KEYS = 'delete_suffixed_keys';
export const RENAME_GROUP_URL = 'rename-group';
export const GET_UI_SETTINGS = "ui-settings";
export const POST_UI_SETTINGS = "ui-settings";
export const GET_USER_LIST = "user-list";
export const POST_MISSING_KEYS = "missing-keys";
export const GET_TRANSLATION_TABLE = "translation-table";
export const GET_SUMMARY_DATA = "summary";
export const GET_SEARCH = "search-data";
export const GET_MISMATCHES = "mismatches";
export const POST_USER_LOCALES = "user_locales";
export const POST_DELETE_TRANSLATION = "delete";
export const POST_UNDELETE_TRANSLATION = "undelete";
export const POST_SHOW_SOURCE = "show_source";
export const GET_ZIP_GROUP_URL = 'zipped_translations';
export const POST_PUBLISH_GROUP_URL = 'publish';
export const POST_DELETE_GROUP_URL = 'delete_all';
export const POST_IMPORT_GROUP_URL = 'import';
export const POST_FIND_REFERENCES_URL = 'find';
export const POST_EDIT_TRANSLATION = 'edit';
export const POST_TRANS_FILTER = 'trans_filters';
