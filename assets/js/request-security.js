(function () {
  'use strict';
  var meta = document.querySelector('meta[name="csrf-token"]');
  if (!meta) return;
  function needsToken(url, method) {
    return new URL(url, location.href).origin === location.origin && !/^(GET|HEAD|OPTIONS)$/i.test(method);
  }
  var originalFetch = window.fetch;
  window.fetch = function (input, options) {
    options = Object.assign({}, options || {});
    var request = input instanceof Request;
    var method = options.method || (request ? input.method : 'GET');
    if (needsToken(request ? input.url : input, method)) {
      var headers = new Headers(options.headers || (request ? input.headers : undefined));
      headers.set('X-CSRF-Token', meta.content);
      options.headers = headers;
    }
    return originalFetch.call(this, input, options);
  };
  var open = XMLHttpRequest.prototype.open;
  var send = XMLHttpRequest.prototype.send;
  XMLHttpRequest.prototype.open = function (method, url) {
    this.crmNeedsToken = needsToken(url, method);
    return open.apply(this, arguments);
  };
  XMLHttpRequest.prototype.send = function () {
    if (this.crmNeedsToken) this.setRequestHeader('X-CSRF-Token', meta.content);
    return send.apply(this, arguments);
  };
  function protectForm(form) {
    if (form.method.toLowerCase() !== 'post' || !needsToken(form.action, 'POST')) return;
    if (!form.querySelector('[name="_csrf_token"]')) {
      var field = document.createElement('input');
      field.type = 'hidden'; field.name = '_csrf_token'; field.value = meta.content;
      form.appendChild(field);
    }
  }
  document.addEventListener('submit', function (event) { protectForm(event.target); }, true);
  var submit = HTMLFormElement.prototype.submit;
  HTMLFormElement.prototype.submit = function () { protectForm(this); return submit.call(this); };
}());
