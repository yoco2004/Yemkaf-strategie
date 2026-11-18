/*
 * Pont entre les simulateurs (écrits pour claude.ai) et l'application Yem'kaf Outils.
 * Les simulateurs appellent window.claude.use('db' | 'user' | 'downloads') ; ce pont répond avec l'API de l'application :
 *   GET  /api/data/<clé>  lit les chiffres partagés ;  PUT /api/data/<clé>  les enregistre (jeton anti-falsification).
 * Les autres personnes voient les changements en moins de 20 secondes.
 */
(function () {
  'use strict';
  var YK = window.YK || {};
  var base = YK.base || '';
  function api(cle, opts) {
    var o = opts || {};
    o.credentials = 'same-origin';
    o.headers = Object.assign({ Accept: 'application/json' }, o.headers || {});
    return fetch(base + '/api/data/' + cle, o);
  }
  function instantane(corps) {
    return {
      exists: !!(corps && corps.data),
      data: function () { return corps ? corps.data : undefined; },
      metadata: { fromCache: false, hasPendingWrites: false }
    };
  }
  var db = {
    doc: function (chemin) {
      var cle = (YK.docs || {})[chemin];
      return {
        onSnapshot: function (rappel, erreur) {
          var dernier = null, arrete = false;
          function lire() {
            if (!cle) return;
            api(cle).then(function (r) {
              if (r.status === 401 || r.status === 403) { var e = new Error('revoked'); e.code = 'revoked'; throw e; }
              if (!r.ok) throw new Error('indisponible');
              return r.json();
            }).then(function (corps) {
              var s = JSON.stringify(corps.data);
              if (s !== dernier) { dernier = s; rappel(instantane(corps)); }
            }).catch(function (e) {
              if (e && e.code === 'revoked') { arrete = true; if (erreur) erreur(e); }
            });
          }
          lire();
          var minuteur = setInterval(function () { if (!arrete && !document.hidden) lire(); }, 20000);
          document.addEventListener('visibilitychange', function () { if (!document.hidden && !arrete) lire(); });
          return function () { arrete = true; clearInterval(minuteur); };
        },
        set: function (donnees) {
          return api(cle, { method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-CSRF': YK.csrf || '' }, body: JSON.stringify(donnees) })
            .then(function (r) {
              var e;
              if (r.status === 403) { e = new Error('lecture seule'); e.code = 'invalid_argument'; throw e; }
              if (r.status === 413) { e = new Error('trop gros'); e.code = 'quota_exceeded'; throw e; }
              if (r.status === 401 || r.status === 400) { e = new Error('session'); e.code = 'denied'; throw e; }
              if (!r.ok) { e = new Error('indisponible'); e.code = 'unavailable'; throw e; }
              return r.json();
            });
        }
      };
    }
  };
  var user = {
    can: function (nom) { return Promise.resolve(nom === 'data.write' ? !!YK.ecriture : null); },
    isOwner: function () { return false; },
    canEdit: function () { return !!YK.ecriture; }
  };
  var downloads = {
    save: function (f) {
      return new Promise(function (ok) {
        var type = /\.csv$/i.test(f.filename) ? 'text/csv;charset=utf-8' : (/\.json$/i.test(f.filename) ? 'application/json' : 'application/octet-stream');
        var a = document.createElement('a');
        a.href = URL.createObjectURL(f.data instanceof Blob ? f.data : new Blob([f.data], { type: type }));
        a.download = f.filename;
        document.body.appendChild(a); a.click();
        setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); ok(); }, 500);
      });
    }
  };
  var services = { db: db, user: user, downloads: downloads };
  window.claude = { use: function (nom) { return Promise.resolve(services[nom] || null); } };
})();
