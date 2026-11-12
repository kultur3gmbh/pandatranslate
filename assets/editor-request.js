(function () {
	'use strict';
	window.PandaTranslateRequest = {
		create: function (config) {
			var refreshing = null;
			function failure(code, label, status) {
				var error = new Error(config.i18n[label] || label);
				error.code = code; error.status = status || 0;
				return error;
			}
			function localUrl(value) {
				var url = new URL(value, window.location.href);
				if (url.origin !== window.location.origin || url.username || url.password) throw failure('request_origin', 'requestOriginError');
				return url.href;
			}
			function fetchLocal(url, options) {
				return Promise.resolve().then(function () {
					return fetch(localUrl(url), Object.assign({}, options, {credentials: 'same-origin', cache: 'no-store', redirect: 'error'}));
				}).catch(function (error) {
					if (error.code) throw error;
					// A lost response may follow a completed translation. Never repeat it automatically.
					throw failure('request_network', 'requestNetworkError');
				});
			}
			function refreshNonce() {
				if (!refreshing) refreshing = fetchLocal(config.nonceUrl, {}).then(function (response) {
					if (!response.ok) throw failure('request_session', 'requestSessionError', response.status);
					return response.text();
				}).then(function (nonce) {
					nonce = nonce.trim();
					if (!/^[a-zA-Z0-9]{10}$/.test(nonce)) throw failure('request_session', 'requestSessionError');
					config.nonce = nonce;
				}).finally(function () { refreshing = null; });
				return refreshing;
			}
			function request(url, body, renewed) {
				var options = {method: body === undefined ? 'GET' : 'POST', headers: {'X-WP-Nonce': config.nonce}};
				if (body !== undefined) { options.headers['Content-Type'] = 'application/json'; options.body = JSON.stringify(body); }
				return fetchLocal(url, options).then(function (response) {
					return response.text().then(function (text) {
						var data;
						try { data = JSON.parse(text); } catch (_) { throw failure('request_response', 'requestResponseError', response.status); }
						if (!response.ok) {
							// WordPress rejects this nonce before executing the route: one retry is safe.
							if (response.status === 403 && data && data.code === 'rest_cookie_invalid_nonce' && !renewed) {
								return refreshNonce().then(function () { return request(url, body, true); });
							}
							var error = failure('request_http', 'requestResponseError', response.status);
							if (data && typeof data.message === 'string') error.message = data.message;
							if (data && typeof data.code === 'string') error.code = data.code;
							throw error;
						}
						if (!data || typeof data !== 'object' || Array.isArray(data)) throw failure('request_response', 'requestResponseError', response.status);
						return data;
					});
				}).catch(function (error) {
					if (error.code) throw error;
					throw failure('request_network', 'requestNetworkError');
				});
			}
			return request;
		}
	};
}());
