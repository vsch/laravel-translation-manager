/**
 * First we will load all of this project's JavaScript dependencies which
 * includes React and other helpers. It's a great starting point while
 * building robust, powerful web applications using React + Laravel.
 */

import "./bootstrap";
import React from 'react';
import ReactDOM from 'react-dom';
import 'bootstrap';
import './helpers/I18n';// needed to have i18n initialized
import './helpers/CreateAppStore';
import '../js-old-scripts/jquery.selection';
import '../js-old-scripts/oldScriptHook';
import '../js-old-scripts/bootstrap';
import '../js-old-scripts/bootstrap-editable';
import '../js-old-scripts/rails';
import '../js-old-scripts/inflection';
import '../js-old-scripts/xregexp-all';
import '../js-old-scripts/translations';
import '../js-old-scripts/translations_page';
import '../css-old-scripts/bootstrap.css';
import '../css-old-scripts/bootstrap-editable.css';
import '../css-old-scripts/translations.css';
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

// get the app url offset for in app routes
let url = document.head.querySelector('meta[name="app-url"]');
let basename = '';
if (url) {
    basename = url.content;
} else {
    console.error('app-url meta data not found');
}

let webUrl = document.head.querySelector('meta[name="web-url"]');

$.fn.OldScriptHooks.APP_URL = '';//url.content;
$.fn.OldScriptHooks.WEB_URL = webUrl;//url.content;
$.fn.OldScriptHooks.BASE_URL = basename;//url.content;

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
