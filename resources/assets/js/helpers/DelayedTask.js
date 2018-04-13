export default class DelayedTask {
    constructor(defaultDelay) {
        this.delayTimer = null;
        this.defaultDelay = defaultDelay;
        this.pendingTask = null;
        // this.restart = this.restart.bind(this);
        // this.cancel = this.cancel.bind(this);
        this.run = this.run.bind(this);
    }

    cancel() {
        if (this.delayTimer) {
            window.clearTimeout(this.delayTimer);
            this.delayTimer = null;
            this.pendingTask = null;
        }
    }
    
    isPending() {
        return this.delayTimer;
    }
    
    run() {
        const task = this.pendingTask;
        this.delayTimer = null;
        this.pendingTask = null;
        
        if (Array.isArray(task)) {
            task.forEach(task => task());
        } else if (task) {
            task();    
        }
    }

    restart(task, delay) {
        this.cancel();
        this.pendingTask = task;
        this.delayTimer = window.setTimeout(this.run, delay !== undefined ? delay : this.defaultDelay);
    }
    
    append(task, delay) {
        const pending = this.pendingTask;
        this.cancel();
        
        if (pending) {
            if (Array.isArray(pending)) { 
                pending.push(task);
            } else {
                this.pendingTask = [pending, task];
            }
        } else {
            this.pendingTask = task;
        }
    }
}
