/* ineffable-box — face switch + throw + recent */
(function () {
  "use strict";

  var service = "omens";
  var services = {};

  var $ = function (id) {
    return document.getElementById(id);
  };

  function api(method, path, body) {
    var opts = { method: method || "GET", headers: {} };
    if (body !== undefined) {
      opts.headers["Content-Type"] = "application/json";
      opts.body = JSON.stringify(body);
    }
    return fetch(path, opts).then(function (res) {
      return res.text().then(function (text) {
        var data = {};
        try {
          data = text ? JSON.parse(text) : {};
        } catch (e) {
          data = { error: text || res.statusText };
        }
        if (!res.ok) {
          throw new Error(data.error || res.statusText || "request failed");
        }
        return data;
      });
    });
  }

  function fmtWhen(unix) {
    if (!unix) return "—";
    var d = new Date(unix * 1000);
    return d.toLocaleString(undefined, {
      month: "short",
      day: "numeric",
      hour: "numeric",
      minute: "2-digit",
      second: "2-digit",
    });
  }

  function tickClock() {
    var el = $("clock");
    if (!el) return;
    el.textContent = "now · " + fmtWhen(Math.floor(Date.now() / 1000));
  }

  function setStatus(msg, isErr) {
    var el = $("status");
    if (!el) return;
    if (!msg) {
      el.hidden = true;
      el.textContent = "";
      return;
    }
    el.hidden = false;
    el.textContent = msg;
    el.classList.toggle("is-err", !!isErr);
  }

  var kickers = {
    omens: "the field is watching · ship the sign",
    hymns: "soft receive · song of the sphere",
    secrets: "pretty wrong · tell me anyway",
  };
  var sendLabels = {
    omens: "FILE IT",
    hymns: "OFFER",
    secrets: "slip it in",
  };

  function applyFace(id) {
    service = id;
    document.body.dataset.service = id;
    document.body.classList.remove("face-omens", "face-hymns", "face-secrets");
    document.body.classList.add("face-" + id);

    var btns = document.querySelectorAll(".ib-face");
    for (var i = 0; i < btns.length; i++) {
      btns[i].classList.toggle("is-on", btns[i].getAttribute("data-service") === id);
    }

    var meta = services[id] || {};
    var name = $("whomName");
    var hint = $("whomHint");
    var kicker = $("kicker");
    var sendBtn = $("sendBtn");
    if (name) name.textContent = meta.whom || meta.title || id;
    if (hint) hint.textContent = meta.hint || "";
    if (kicker) kicker.textContent = kickers[id] || "ship at notice";
    if (sendBtn) sendBtn.textContent = sendLabels[id] || "THROW IN";

    try {
      localStorage.setItem("ineffable-box-service", id);
    } catch (e) {}

    loadRecent();
  }

  function loadRecent() {
    var ul = $("recentList");
    if (!ul) return;
    api("GET", "/api/drops?service=" + encodeURIComponent(service))
      .then(function (data) {
        var drops = data.drops || [];
        ul.innerHTML = "";
        if (!drops.length) {
          var empty = document.createElement("li");
          empty.className = "ib-empty";
          empty.textContent = "no drops yet";
          ul.appendChild(empty);
          return;
        }
        drops.forEach(function (d) {
          var li = document.createElement("li");
          var when = document.createElement("span");
          when.className = "ib-drop-when";
          var chip = d.chip || d.id || "";
          when.textContent =
            fmtWhen(d.event_unix || d.saved_at) + (chip ? " · " + chip : "");
          var body = document.createElement("span");
          body.className = "ib-drop-body";
          body.textContent = d.body || "";
          li.appendChild(when);
          li.appendChild(body);
          if (d.tags_raw) {
            var tags = document.createElement("span");
            tags.className = "ib-drop-tags";
            tags.textContent = d.tags_raw;
            li.appendChild(tags);
          }
          ul.appendChild(li);
        });
      })
      .catch(function (e) {
        ul.innerHTML = "";
        var li = document.createElement("li");
        li.className = "ib-empty";
        li.textContent = String(e.message || e);
        ul.appendChild(li);
      });
  }

  function onSubmit(e) {
    e.preventDefault();
    var bodyEl = $("body");
    var tagsEl = $("tags");
    var btn = $("sendBtn");
    var text = bodyEl ? bodyEl.value : "";
    var tags = tagsEl ? tagsEl.value : "";
    if (!text.trim()) {
      setStatus("need a notice", true);
      if (bodyEl) bodyEl.focus();
      return;
    }
    if (btn) btn.disabled = true;
    setStatus("throwing…");
    api("POST", "/api/drop", {
      service: service,
      body: text,
      tags_raw: tags,
      event_unix: Math.floor(Date.now() / 1000),
    })
      .then(function () {
        if (bodyEl) bodyEl.value = "";
        if (tagsEl) tagsEl.value = "";
        setStatus("in the box");
        loadRecent();
        if (bodyEl) bodyEl.focus();
        setTimeout(function () {
          setStatus("");
        }, 1600);
      })
      .catch(function (err) {
        setStatus(String(err.message || err), true);
      })
      .finally(function () {
        if (btn) btn.disabled = false;
      });
  }

  function boot() {
    tickClock();
    setInterval(tickClock, 1000);

    document.querySelectorAll(".ib-face").forEach(function (btn) {
      btn.addEventListener("click", function () {
        applyFace(btn.getAttribute("data-service") || "omens");
      });
    });

    var form = $("dropForm");
    if (form) form.addEventListener("submit", onSubmit);

    // Ctrl+Enter to throw
    var bodyEl = $("body");
    if (bodyEl) {
      bodyEl.addEventListener("keydown", function (e) {
        if (e.key === "Enter" && (e.ctrlKey || e.metaKey)) {
          e.preventDefault();
          if (form) form.requestSubmit();
        }
      });
    }

    window.DECK_ROM_MENU = [
      { label: "New window", action: "new_window" },
      { label: "Hard refresh", action: "hard_refresh" },
      { sep: true },
      {
        label: "Oman · omens",
        run: function () {
          applyFace("omens");
        },
      },
      {
        label: "Jasmine · hymns",
        run: function () {
          applyFace("hymns");
        },
      },
      {
        label: "Teehee · secrets",
        run: function () {
          applyFace("secrets");
        },
      },
    ];
    window.DECK_ROM_MENU_EXTRAS = [];

    api("GET", "/api/services")
      .then(function (data) {
        (data.services || []).forEach(function (s) {
          services[s.id] = s;
        });
        var saved = "omens";
        try {
          saved = localStorage.getItem("ineffable-box-service") || "omens";
        } catch (e) {}
        if (!services[saved]) saved = "omens";
        applyFace(saved);
      })
      .catch(function () {
        applyFace("omens");
      });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
