import { applyMiddleware, createStore } from "redux";
import thunk from "redux-thunk";
import { boxState } from "boxed-immutable";

let globalHandlers = {};

export function registerGlobalSettingsHandler(handler) {
    globalHandlers[handler.globalKey] = handler;
}

let globalSettingsReducer = (state = {}, action) => {
    if (action.type === '@@redux/INIT') {
        // TODO: set defaults for all registered
        let tmp = 0;
    } else {
        if (globalHandlers.hasOwnProperty(action.type)) {
            let handler = globalHandlers[action.type];

            let actionState = {};
            actionState[handler.globalKey] = action;

            // if (handler.globalKey === "globalMismatches" && (state.hasOwnProperty(handler.globalKey) && !state[handler.globalKey].isLoaded && actionCopy.isLoaded)) { 
            //     let tmp = 0;
            // }
            let newState = Object.assign({}, state, actionState);
            return newState;
        }
    }

    return state;
};

const store = createStore(
    globalSettingsReducer,
    applyMiddleware(thunk)
);

export const boxedState_$ = (function () {
    const onDemand = boxState(() => {
        return store.getState();
    }, undefined);

    // invalidate the boxed state
    const subscribe = store.subscribe(() => {
        onDemand.cancel();
    });
    return onDemand;
})();

export default store;
