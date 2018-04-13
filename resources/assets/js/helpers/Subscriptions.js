export function unsubscribeListener(subscribers, listener) {
    const index = subscribers.indexOf(listener);
    if (index >= 0) subscribers.splice(index, 1);
}

export function subscribeListener(subscribers, listener) {
    const index = subscribers.indexOf(listener);
    if (index < 0) subscribers.push(listener);
    return () => unsubscribeListener(subscribers, listener);
}

export function informListeners(origSubscribers, ...params) {
    const subscribers = Object.assign([], origSubscribers);

    const iMax = subscribers.length;
    let unsubscribe = null;
    for (let i = 0; i < iMax; i++) {
        const subscriber = subscribers[i];
        if (!subscriber) {
            const tmp = 0;
        }
        try {
            subscriber.apply(undefined, params);
        } catch (e) {
            if (!unsubscribe) {
                unsubscribe = [];
            }
            unsubscribe.push(subscriber);
            console.error("GlobalSettingsHandler listener error for ", i, subscriber, params, subscribers, e)
        }
    }

    if (unsubscribe) {
        unsubscribe.forEach((subscriber) => unsubscribeListener(subscribers, subscriber));
    }
}

export class Subscriber {
    constructor() {
        this._unsubscribe = [];
        let iMax = arguments.length - 1;
        let callBack = arguments[iMax];

        let signal = (function () {
            if (this._unsubscribe) {
                callBack();
            }
        }).bind(this);

        for (let i = 0; i < iMax; i++) {
            let target = arguments[i];
            this._unsubscribe.push(target.subscribe(signal));
        }

        this.unsubscribe = this.unsubscribe.bind(this);
    }

    unsubscribe() {
        let tmp = this._unsubscribe;
        this._unsubscribe = null;
        let iMax = tmp.length;
        for (let i = 0; i < iMax; i++) {
            tmp[i]();
        }
    }
}
