import axios from 'axios';

// Laravel sets an XSRF-TOKEN cookie; axios reads it and sends X-XSRF-TOKEN
// automatically on same-origin requests.
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common.Accept = 'application/json';

window.axios = axios;
