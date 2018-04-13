/**
 * First we will load all of this project's JavaScript dependencies which
 * includes React and other helpers. It's a great starting point while
 * building robust, powerful web applications using React + Laravel.
 */

import "./bootstrap";
import React from 'react';
import ReactDOM from 'react-dom';
import 'bootstrap';
// needed to have i18n initialized
import './helpers/I18n';
import './helpers/CreateAppStore';
// import './helpers/GlobalSettings';
// import 'jquery';
// import 'bootstrap';
// import './bootstrap/js/jquery';
import './bootstrap/js/jquery.selection';
import './bootstrap/js/oldScriptHook';
import './bootstrap/js/bootstrap';
import './bootstrap/js/bootstrap-editable';
import './bootstrap/js/rails';
import './bootstrap/js/inflection';
import './bootstrap/js/xregexp-all';
import './bootstrap/js/translations';
import './bootstrap/js/translations_page';
import './bootstrap/css/bootstrap.css';
// import './bootstrap/css/bootstrap-theme.css';
import './bootstrap/css/bootstrap-editable.css';
import './bootstrap/css/translations.css';
import $ from 'jquery';
/**
 * Next, we will create a fresh React component instance and attach it to
 * the page. Then, you may begin adding components to this application
 * or customize the JavaScript scaffolding to fit your unique needs.
 */
import './helpers/LanguageSynchronizer';
import { BrowserRouter as Router } from "react-router-dom";
import { I18nextProvider } from 'react-i18next';
import { Provider } from "react-redux";
import App from './components/App';
import store from "./helpers/CreateAppStore";
import i18n from './helpers/I18n';
import { apiURL } from "./helpers/ApiRoutes";

// needed to have react hot loader patch before the app
// import "react-hot-loader/patch";
// import 'babel-polyfill'
// import registerServiceWorker from './registerServiceWorker';
// import './sidebar.css';
// import 'react-x-editable';

// get the app url offset for in app routes
let url = document.head.querySelector('meta[name="app-url"]');
let basename = '';
if (url) {
    basename = url.content;
} else {
    console.error('app-url meta data not found');
}

$.fn.OldScriptHooks.APP_URL = '';//url.content;
$.fn.OldScriptHooks.BASE_URL = basename;//url.content;
let translationURL = apiURL(window.axios.defaults.baseURL, 'get/{{ns}}::messages/{{lng}}');


//ReactDOM.render(<App />, document.getElementById('root'));
if (document.getElementById('root')) {
    ReactDOM.render(
        <I18nextProvider i18n={i18n}>
            <Provider store={store}>
                <Router basename={basename}>
                    <App/>
                </Router>
            </Provider>
        </I18nextProvider>
        , document.getElementById('root'));
}
