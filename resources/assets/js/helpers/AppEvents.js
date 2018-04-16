import { informListeners, subscribeListener } from './Subscriptions';

class AppEvents {
    constructor() {
        this.eventSubscribers = {};
        this.events = {};
    }
    
    registerEvent(eventName) {
        this.events[eventName] = true;
    }

    subscribe(eventName, listener) {
        if (!this.events[eventName]) { 
            throw "IllegalStateException, event " + eventName + " has not been registered";
        }
        if (!this.eventSubscribers[eventName]) { 
            this.eventSubscribers[eventName] = []; 
        }
        return subscribeListener(this.eventSubscribers[eventName], listener);
    }

    fireEvent(eventName) {
        if (!this.events[eventName]) {
            throw "IllegalStateException, event " + eventName + " has not been registered";
        }
        
        if (this.eventSubscribers[eventName]) {
            informListeners(this.eventSubscribers[eventName], ...Array.prototype.splice.call(arguments, 1));
        }
    }
}

const appEvents = new AppEvents();

export default appEvents;

