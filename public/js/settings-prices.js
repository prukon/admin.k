// resources/js/kids-tooltip.js
(function(window2) {
  "use strict";
  if (window2.KidsCrmTooltip) {
    return;
  }
  const TOOLTIP_CLASS = "ulp-assignment-paid-tooltip";
  const LIST_TOOLTIP_CLASS = "kids-hover-list-tooltip ulp-assignment-paid-tooltip";
  const SCOPES = {
    list: ".js-kids-hover-list-dropdown",
    text: ".js-dt-cell-ellipsis-tooltip",
    hint: '[data-kids-tooltip-hint][data-bs-toggle="tooltip"]',
    manualPaid: '.ulp-paid-manual-hint[data-bs-toggle="tooltip"], .user-manual-info-icon[data-bs-toggle="tooltip"]',
    generic: '[data-bs-toggle="tooltip"]'
  };
  function escapeHtml(value) {
    return String(value).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
  }
  function decodeHtmlEntities(value) {
    let s = String(value || "");
    if (s.indexOf("&") === -1) {
      return s;
    }
    let prev;
    let guard = 0;
    do {
      prev = s;
      s = s.replace(/&amp;/g, "&").replace(/&quot;/g, '"').replace(/&#0*39;/g, "'").replace(/&#x27;/gi, "'").replace(/&lt;/g, "<").replace(/&gt;/g, ">");
      guard++;
    } while (s !== prev && guard < 5);
    return s;
  }
  function normalizeDisplayText(value) {
    return decodeHtmlEntities(String(value || "")).trim();
  }
  function normalizeItems(items) {
    if (!Array.isArray(items)) {
      return [];
    }
    return items.map(function(item) {
      return String(item || "").trim();
    }).filter(function(item) {
      return item !== "";
    });
  }
  function buildListTooltipTitle(items) {
    const listItems = items.map(function(item) {
      return "<li>" + escapeHtml(item) + "</li>";
    }).join("");
    return '<ul class="kids-hover-list-tooltip__list">' + listItems + "</ul>";
  }
  function bootstrapAvailable() {
    return typeof bootstrap !== "undefined" && bootstrap.Tooltip;
  }
  function disposeElement(el) {
    if (!bootstrapAvailable()) {
      return;
    }
    const existing = bootstrap.Tooltip.getInstance(el);
    if (existing) {
      existing.dispose();
    }
  }
  function initListElement(el) {
    disposeElement(el);
    let items = [];
    const rawItems = el.getAttribute("data-kids-hover-list-items");
    if (rawItems) {
      try {
        items = normalizeItems(JSON.parse(rawItems));
      } catch (error) {
        items = [];
      }
    }
    if (items.length >= 2) {
      el.setAttribute("title", buildListTooltipTitle(items));
    }
    new bootstrap.Tooltip(el, {
      html: true,
      placement: el.getAttribute("data-bs-placement") || "top",
      customClass: LIST_TOOLTIP_CLASS,
      trigger: "hover focus"
    });
  }
  function isTextOverflowing(el) {
    return el.scrollWidth > el.clientWidth + 1;
  }
  function initTextElement(el) {
    disposeElement(el);
    el.classList.remove("dt-cell-ellipsis--truncated");
    el.removeAttribute("data-bs-toggle");
    const isEllipsisCell = el.classList.contains("dt-cell-ellipsis");
    if (isEllipsisCell) {
      el.removeAttribute("title");
      if (!isTextOverflowing(el)) {
        return;
      }
      const title = el.getAttribute("data-dt-ellipsis-title") || el.textContent.trim();
      if (!title) {
        return;
      }
      el.setAttribute("title", title);
      el.setAttribute("data-bs-toggle", "tooltip");
      el.setAttribute("data-bs-placement", "top");
      el.setAttribute("data-bs-custom-class", TOOLTIP_CLASS);
      el.classList.add("dt-cell-ellipsis--truncated");
    }
    if (!el.getAttribute("data-bs-toggle")) {
      return;
    }
    new bootstrap.Tooltip(el, {
      placement: el.getAttribute("data-bs-placement") || "top",
      customClass: el.getAttribute("data-bs-custom-class") || TOOLTIP_CLASS,
      trigger: "hover focus"
    });
  }
  function initHintElement(el) {
    disposeElement(el);
    new bootstrap.Tooltip(el, {
      placement: el.getAttribute("data-bs-placement") || "top",
      customClass: TOOLTIP_CLASS,
      trigger: "hover focus"
    });
  }
  function initManualPaidElement(el) {
    disposeElement(el);
    new bootstrap.Tooltip(el, {
      placement: el.getAttribute("data-bs-placement") || "top",
      customClass: el.getAttribute("data-bs-custom-class") || TOOLTIP_CLASS,
      trigger: "hover focus"
    });
  }
  function initGenericElement(el) {
    disposeElement(el);
    if (!el.getAttribute("title")) {
      const bsTitle = el.getAttribute("data-bs-title");
      if (bsTitle) {
        el.setAttribute("title", bsTitle);
      }
    }
    new bootstrap.Tooltip(el, {
      html: el.getAttribute("data-bs-html") === "true",
      placement: el.getAttribute("data-bs-placement") || "top",
      customClass: el.getAttribute("data-bs-custom-class") || TOOLTIP_CLASS,
      trigger: "hover focus"
    });
  }
  function resolveScopes(options) {
    if (!options || !options.scopes) {
      return ["list", "text", "hint"];
    }
    return options.scopes.filter(function(scope) {
      return Object.prototype.hasOwnProperty.call(SCOPES, scope);
    });
  }
  function queryElements(root, scopes) {
    const base = root || document;
    const elements = [];
    scopes.forEach(function(scope) {
      base.querySelectorAll(SCOPES[scope]).forEach(function(el) {
        elements.push({ scope, el });
      });
    });
    return elements;
  }
  const KidsCrmTooltip = {
    escapeHtml,
    decodeHtmlEntities,
    normalizeDisplayText,
    renderText: function(text, options) {
      options = options || {};
      const raw = normalizeDisplayText(text);
      if (raw === "") {
        return options.emptyHtml || '<span class="dt-cell-empty text-muted">\u2014</span>';
      }
      const escaped = escapeHtml(raw);
      return '<span class="dt-cell-ellipsis js-dt-cell-ellipsis-tooltip" data-dt-ellipsis-title="' + escaped + '" tabindex="0" aria-label="' + escaped + '">' + escaped + "</span>";
    },
    renderLink: function(text, options) {
      options = options || {};
      const inner = KidsCrmTooltip.renderText(text, options);
      if (inner.indexOf("js-dt-cell-ellipsis-tooltip") === -1) {
        return inner;
      }
      const linkClass = String(options.linkClass || "").trim();
      const extraAttrs = options.extraAttrs || "";
      const hrefOption = options.href;
      const href = hrefOption != null && String(hrefOption).trim() !== "" ? String(hrefOption) : "javascript:void(0);";
      return '<a href="' + escapeHtml(href) + '" class="' + escapeHtml(linkClass) + '" ' + extraAttrs + ">" + inner + "</a>";
    },
    renderList: function(shortLabel, items, options) {
      options = options || {};
      const titles = normalizeItems(items);
      const label = String(shortLabel || "").trim();
      if (titles.length === 0) {
        return options.emptyHtml || '<span class="dt-cell-empty text-muted">\u2014</span>';
      }
      const visibleLabel = label !== "" ? label : titles.join(", ");
      const minItemsForHover = typeof options.minItemsForHover === "number" ? options.minItemsForHover : 2;
      if (titles.length < minItemsForHover) {
        return escapeHtml(visibleLabel);
      }
      return '<span class="js-kids-hover-list-dropdown kids-hover-list-dropdown__trigger" data-bs-toggle="tooltip" data-bs-html="true" data-bs-placement="top" data-bs-custom-class="' + LIST_TOOLTIP_CLASS + '" data-kids-hover-list-items="' + escapeHtml(JSON.stringify(titles)) + '" title="' + escapeHtml(buildListTooltipTitle(titles)) + '" tabindex="0" aria-label="' + escapeHtml(visibleLabel) + '">' + escapeHtml(visibleLabel) + "</span>";
    },
    init: function(root, options) {
      if (!bootstrapAvailable()) {
        return;
      }
      const scopes = resolveScopes(options);
      queryElements(root, scopes).forEach(function(entry) {
        if (entry.scope === "list") {
          initListElement(entry.el);
        } else if (entry.scope === "text") {
          initTextElement(entry.el);
        } else if (entry.scope === "hint") {
          initHintElement(entry.el);
        } else if (entry.scope === "manualPaid") {
          initManualPaidElement(entry.el);
        } else if (entry.scope === "generic") {
          initGenericElement(entry.el);
        }
      });
    },
    dispose: function(root, options) {
      if (!bootstrapAvailable()) {
        return;
      }
      const scopes = resolveScopes(options);
      queryElements(root, scopes).forEach(function(entry) {
        disposeElement(entry.el);
      });
    },
    bindDataTable: function(tableElement) {
      if (!tableElement) {
        return;
      }
      const init = function() {
        requestAnimationFrame(function() {
          KidsCrmTooltip.init(tableElement, { scopes: ["text", "list", "manualPaid"] });
        });
      };
      if (typeof $ !== "undefined" && $.fn.DataTable && $.fn.DataTable.isDataTable(tableElement)) {
        $(tableElement).off("draw.dt.kidsCrmTooltip").on("draw.dt.kidsCrmTooltip", init);
      }
      init();
    }
  };
  window2.KidsCrmTooltip = KidsCrmTooltip;
  document.addEventListener("DOMContentLoaded", function() {
    KidsCrmTooltip.init(document, { scopes: ["hint"] });
  });
})(window);

// resources/js/setting-prices-manual-paid-modal.js
(function(window2) {
  "use strict";
  function showManualPaidCommentModal(title, hint, onConfirm) {
    const modalEl = document.getElementById("manualUserPricePaidModal");
    if (!modalEl) {
      console.error("manualUserPricePaidModal not found");
      return;
    }
    const titleEl = document.getElementById("manualUserPricePaidModalLabel");
    const hintEl = document.getElementById("manualUserPricePaidModalHint");
    const ta = document.getElementById("manualUserPricePaidComment");
    const errEl = document.getElementById("manualUserPricePaidCommentError");
    const confirmBtn = document.getElementById("manualUserPricePaidConfirmBtn");
    if (titleEl)
      titleEl.textContent = title || "\u041A\u043E\u043C\u043C\u0435\u043D\u0442\u0430\u0440\u0438\u0439";
    if (hintEl)
      hintEl.textContent = hint || "";
    if (ta) {
      ta.value = "";
      ta.classList.remove("is-invalid");
    }
    if (errEl) {
      errEl.style.display = "none";
      errEl.textContent = "";
    }
    const newBtn = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newBtn, confirmBtn);
    newBtn.addEventListener("click", function() {
      const comment = ta && ta.value ? ta.value.trim() : "";
      if (comment.length < 3) {
        if (ta)
          ta.classList.add("is-invalid");
        if (errEl) {
          errEl.style.display = "block";
          errEl.textContent = "\u0412\u0432\u0435\u0434\u0438\u0442\u0435 \u043A\u043E\u043C\u043C\u0435\u043D\u0442\u0430\u0440\u0438\u0439 \u043D\u0435 \u043A\u043E\u0440\u043E\u0447\u0435 3 \u0441\u0438\u043C\u0432\u043E\u043B\u043E\u0432.";
        }
        return;
      }
      if (ta)
        ta.classList.remove("is-invalid");
      if (errEl)
        errEl.style.display = "none";
      try {
        if (typeof onConfirm === "function") {
          onConfirm(comment);
        }
      } finally {
        const inst = bootstrap.Modal.getInstance(modalEl);
        if (inst)
          inst.hide();
      }
    });
    if (typeof window2.showModalQueued === "function") {
      window2.showModalQueued("manualUserPricePaidModal", { backdrop: "static", keyboard: false });
    } else {
      bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: "static", keyboard: false }).show();
    }
  }
  window2.showManualPaidCommentModal = showManualPaidCommentModal;
  function initManualPaidBadgeTooltips(root) {
    if (!window2.KidsCrmTooltip) {
      return;
    }
    window2.KidsCrmTooltip.dispose(root, { scopes: ["manualPaid"] });
    window2.KidsCrmTooltip.init(root, { scopes: ["manualPaid"] });
  }
  window2.initManualPaidBadgeTooltips = initManualPaidBadgeTooltips;
})(window);

// resources/js/settings-prices.js
document.addEventListener("DOMContentLoaded", function() {
  $.ajaxSetup({
    headers: {
      "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
    }
  });
  requestAnimationFrame(function() {
    const leftBar = document.getElementById("left_bar");
    if (leftBar && window.KidsCrmTooltip) {
      window.KidsCrmTooltip.init(leftBar, { scopes: ["text"] });
    }
    initTeamPackageRows();
  });
  let usersPrice = [];
  let lastCanManageManualPaid = false;
  let lastUsersTeam = [];
  let lastTeamId = null;
  let lastLessonPackages = [];
  let editingMonthlyUserId = null;
  function disposeTeamOkTooltip(okBtn) {
    if (!okBtn || typeof bootstrap === "undefined" || !bootstrap.Tooltip) {
      return;
    }
    const existing = bootstrap.Tooltip.getInstance(okBtn);
    if (existing) {
      existing.dispose();
    }
    document.querySelectorAll(".tooltip.show").forEach(function(tipEl) {
      if (tipEl.id && okBtn.getAttribute("aria-describedby") === tipEl.id) {
        tipEl.remove();
      }
    });
    okBtn.removeAttribute("aria-describedby");
  }
  function syncTeamOkDisabledHint(okBtn, isDisabled) {
    if (!okBtn) {
      return;
    }
    disposeTeamOkTooltip(okBtn);
    okBtn.disabled = false;
    okBtn.classList.remove("disabled");
    if (isDisabled) {
      okBtn.setAttribute("aria-disabled", "true");
      okBtn.classList.add("is-visually-disabled");
      okBtn.setAttribute("title", "\u0412\u044B\u0431\u0435\u0440\u0438\u0442\u0435 \u0430\u0431\u043E\u043D\u0435\u043C\u0435\u043D\u0442");
      okBtn.setAttribute("data-kids-tooltip-hint", "1");
      okBtn.setAttribute("data-bs-toggle", "tooltip");
      okBtn.setAttribute("data-bs-placement", "top");
      okBtn.setAttribute("data-bs-custom-class", "ulp-assignment-paid-tooltip");
      return;
    }
    okBtn.removeAttribute("aria-disabled");
    okBtn.classList.remove("is-visually-disabled");
    okBtn.removeAttribute("title");
    okBtn.removeAttribute("data-kids-tooltip-hint");
    okBtn.removeAttribute("data-bs-toggle");
    okBtn.removeAttribute("data-bs-placement");
    okBtn.removeAttribute("data-bs-custom-class");
  }
  function refreshTeamOkTooltips() {
    const leftBar = document.getElementById("left_bar");
    if (!leftBar || !window.KidsCrmTooltip) {
      return;
    }
    window.KidsCrmTooltip.dispose(leftBar, { scopes: ["hint"] });
    window.KidsCrmTooltip.init(leftBar, { scopes: ["hint"] });
  }
  function isTeamOkDisabled(okBtn) {
    if (!okBtn) {
      return true;
    }
    return okBtn.getAttribute("aria-disabled") === "true" || !!okBtn.disabled;
  }
  function syncTeamRowPackageUi(rowEl) {
    if (!rowEl) {
      return;
    }
    const select = rowEl.querySelector(".setting-prices-team-package-select");
    const priceEl = rowEl.querySelector(".setting-prices-team-price-value");
    const okBtn = rowEl.querySelector(".ok");
    if (!select || !priceEl) {
      return;
    }
    const pkgVal = select.value;
    const selectedOpt = select.options[select.selectedIndex];
    const legacyPrice = rowEl.getAttribute("data-legacy-price");
    if (pkgVal && selectedOpt) {
      const pkgPrice = selectedOpt.getAttribute("data-price");
      priceEl.textContent = formatPriceValue(pkgPrice);
      priceEl.setAttribute("data-price", String(pkgPrice != null ? pkgPrice : ""));
      syncTeamOkDisabledHint(okBtn, false);
    } else {
      priceEl.textContent = formatPriceValue(legacyPrice);
      priceEl.setAttribute("data-price", String(legacyPrice != null ? legacyPrice : "0"));
      syncTeamOkDisabledHint(okBtn, true);
    }
  }
  function initTeamPackageRows() {
    document.querySelectorAll("#left_bar .wrap-team").forEach(function(rowEl) {
      syncTeamRowPackageUi(rowEl);
    });
    syncSetPriceAllTeamsButton();
    refreshTeamOkTooltips();
  }
  function syncSetPriceAllTeamsButton() {
    const btn = document.getElementById("set-price-all-teams");
    if (!btn) {
      return;
    }
    let hasAny = false;
    document.querySelectorAll("#left_bar .setting-prices-team-package-select").forEach(function(sel) {
      if (sel.value) {
        hasAny = true;
      }
    });
    btn.disabled = !hasAny;
  }
  function loadTeamUsersRightColumn(teamId) {
    if (!teamId) {
      return;
    }
    const selectedDate = getSelectedMonthLabel();
    const applyBtn = document.querySelector("#right_bar .btn-setting-prices");
    if (applyBtn) {
      applyBtn.setAttribute("disabled", "disabled");
    }
    editingMonthlyUserId = null;
    const csrf = $('meta[name="csrf-token"]').attr("content");
    $.ajax({
      url: "/admin/setting-prices/get-team-price",
      method: "POST",
      contentType: "application/json",
      dataType: "json",
      headers: {
        "X-CSRF-TOKEN": csrf,
        "Accept": "application/json"
      },
      data: JSON.stringify({
        teamId,
        selectedDate
      }),
      success: function(response) {
        if (response.success) {
          usersPrice = response.usersPrice;
          lastLessonPackages = Array.isArray(response.lessonPackages) ? response.lessonPackages : [];
          lastTeamId = String(teamId);
          const usersTeam = response.usersTeam;
          const canManage = !!response.can_manage_manual_paid;
          renderUsersRightColumn(usersTeam, usersPrice, canManage);
        }
      },
      error: function(xhr, status, error) {
        console.error("\u041E\u0448\u0438\u0431\u043A\u0430: " + error);
        console.error("\u0421\u0442\u0430\u0442\u0443\u0441: " + status);
        console.dir(xhr);
      }
    });
  }
  function escapeAttr(s) {
    if (s == null || s === "") {
      return "";
    }
    return String(s).replace(/&/g, "&amp;").replace(/"/g, "&quot;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
  }
  function escapeHtml(s) {
    if (s == null || s === "") {
      return "";
    }
    return String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
  }
  function formatPriceValue(price) {
    const n = Number(price);
    if (!Number.isFinite(n)) {
      return "0";
    }
    if (Math.abs(n - Math.round(n)) < 1e-3) {
      return String(Math.round(n));
    }
    return n.toFixed(2);
  }
  function findLessonPackage(packageId) {
    if (packageId == null || packageId === "") {
      return null;
    }
    const id = String(packageId);
    return lastLessonPackages.find(function(p) {
      return String(p.id) === id;
    }) || null;
  }
  function buildPackageSelectOptions(selectedPackageId) {
    let html = '<option value="">\u0411\u0435\u0437 \u0430\u0431\u043E\u043D\u0435\u043C\u0435\u043D\u0442\u0430</option>';
    for (let i = 0; i < lastLessonPackages.length; i++) {
      const pkg = lastLessonPackages[i];
      const selected = selectedPackageId != null && String(pkg.id) === String(selectedPackageId) ? " selected" : "";
      html += '<option value="' + escapeAttr(pkg.id) + '"' + selected + ">" + escapeHtml(pkg.name) + "</option>";
    }
    return html;
  }
  function getSelectedMonthLabel() {
    const sel = document.getElementById("single-select-date");
    if (!sel || !sel.options[sel.selectedIndex]) {
      return "";
    }
    return sel.options[sel.selectedIndex].textContent;
  }
  function clearTeamRowHighlight() {
    document.querySelectorAll("#left_bar .wrap-team").forEach(function(el) {
      el.classList.remove("wrap-team--active");
    });
  }
  function openTeamDetail(rowEl) {
    if (!rowEl) {
      return;
    }
    clearTeamRowHighlight();
    rowEl.classList.add("wrap-team--active");
    lastTeamId = rowEl.id || null;
    loadTeamUsersRightColumn(rowEl.id);
  }
  function effectivePaidFromUserPrice(row) {
    if (typeof row.effective_is_paid !== "undefined") {
      return !!row.effective_is_paid;
    }
    return !!row.is_paid;
  }
  function syncUsersPriceFromDom() {
    const userRows = document.querySelectorAll("#right_bar .wrap-users .setting-prices-user-card");
    for (let j = 0; j < userRows.length; j++) {
      const userId = userRows[j].getAttribute("data-user-id");
      if (!userId) {
        continue;
      }
      const priceInput = userRows[j].querySelector(".setting-prices-monthly-price-input");
      const packageSelect = userRows[j].querySelector(".setting-prices-monthly-package-select");
      const idx = usersPrice.findIndex(function(u) {
        return String(u.user_id) === String(userId);
      });
      if (idx < 0) {
        continue;
      }
      if (priceInput) {
        usersPrice[idx].price = priceInput.value;
      }
      if (packageSelect) {
        const pkgVal = packageSelect.value;
        usersPrice[idx].lesson_package_id = pkgVal !== "" ? parseInt(pkgVal, 10) : null;
      }
    }
  }
  function postManualPaid(userId, teamId, selectedDate, mode, comment, errorEl2) {
    const csrf = $('meta[name="csrf-token"]').attr("content");
    return $.ajax({
      url: "/admin/setting-prices/manual-paid",
      method: "POST",
      contentType: "application/json",
      dataType: "json",
      headers: {
        "X-CSRF-TOKEN": csrf,
        "Accept": "application/json"
      },
      data: JSON.stringify({
        user_id: userId,
        team_id: teamId,
        selectedDate,
        mode,
        comment
      })
    }).done(function(res) {
      if (res && res.success && res.user_price) {
        syncUsersPriceFromDom();
        const updated = res.user_price;
        const idx = usersPrice.findIndex(function(u) {
          return String(u.user_id) === String(updated.user_id);
        });
        if (idx >= 0) {
          usersPrice[idx] = updated;
        }
        editingMonthlyUserId = null;
        renderUsersRightColumn(lastUsersTeam, usersPrice, lastCanManageManualPaid);
        if (errorEl2) {
          errorEl2.style.display = "none";
          errorEl2.textContent = "";
        }
      }
    }).fail(function(xhr) {
      let msg = "\u041D\u0435 \u0443\u0434\u0430\u043B\u043E\u0441\u044C \u0441\u043E\u0445\u0440\u0430\u043D\u0438\u0442\u044C \u0440\u0443\u0447\u043D\u0443\u044E \u043E\u0442\u043C\u0435\u0442\u043A\u0443.";
      if (xhr.responseJSON) {
        if (xhr.responseJSON.message) {
          msg = xhr.responseJSON.message;
        }
        const errs = xhr.responseJSON.errors;
        if (errs && errs.record && errs.record[0]) {
          msg = errs.record[0];
        }
        if (errs && errs.comment && errs.comment[0]) {
          msg = errs.comment[0];
        }
      }
      if (errorEl2) {
        errorEl2.style.display = "block";
        errorEl2.textContent = msg;
      } else {
        console.error(msg);
      }
    });
  }
  function renderUsersRightColumn(usersTeam, usersPriceList, canManage) {
    lastCanManageManualPaid = !!canManage;
    lastUsersTeam = usersTeam || [];
    const rightBar = $(".wrap-users");
    const rightBarEl = rightBar.get(0);
    if (rightBarEl && window.KidsCrmTooltip) {
      window.KidsCrmTooltip.dispose(rightBarEl, { scopes: ["text", "manualPaid", "hint"] });
    }
    rightBar.empty();
    try {
      rightBar.attr("data-users-team-json", JSON.stringify(usersTeam || []));
    } catch (e) {
      rightBar.removeAttr("data-users-team-json");
    }
    const selectedDate = getSelectedMonthLabel();
    rightBar.attr("data-selected-date", selectedDate);
    for (let i = 0; i < usersPriceList.length; i++) {
      const up = usersPriceList[i];
      const userTeam = usersTeam.find((team) => team.id === up.user_id);
      const eff = effectivePaidFromUserPrice(up);
      const last = userTeam && userTeam.lastname ? String(userTeam.lastname).trim() : "";
      const first = userTeam && userTeam.name ? String(userTeam.name).trim() : "";
      const userNameFormatted = i + 1 + ". " + (last || first ? `${last} ${first}`.trim() : "\u0418\u043C\u044F \u043D\u0435 \u043D\u0430\u0439\u0434\u0435\u043D\u043E");
      const uid = userTeam ? String(userTeam.id) : "";
      const hasManual = up.is_manual_paid !== null && up.is_manual_paid !== void 0;
      const noteRaw = up.manual_paid_note != null && String(up.manual_paid_note).trim() !== "" ? String(up.manual_paid_note) : "";
      const noteForTitle = hasManual ? noteRaw !== "" ? noteRaw : "\u041A\u043E\u043C\u043C\u0435\u043D\u0442\u0430\u0440\u0438\u0439 \u043A \u0440\u0443\u0447\u043D\u043E\u043C\u0443 \u0438\u0437\u043C\u0435\u043D\u0435\u043D\u0438\u044E \u043D\u0435 \u0437\u0430\u043F\u043E\u043B\u043D\u0435\u043D." : "";
      let infoIcon = "";
      if (hasManual) {
        infoIcon = '<i class="fa fa-info-circle user-manual-info-icon" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="ulp-assignment-paid-tooltip" title="' + escapeAttr(noteForTitle) + '" aria-label="\u041A\u043E\u043C\u043C\u0435\u043D\u0442\u0430\u0440\u0438\u0439 \u043A \u0440\u0443\u0447\u043D\u043E\u0439 \u043E\u0442\u043C\u0435\u0442\u043A\u0435 \u043E\u043F\u043B\u0430\u0442\u044B"></i>';
      }
      let pencilHtml = "";
      if (canManage && uid) {
        pencilHtml = '<button type="button" class="btn btn-link btn-sm p-0 user-price-manual-edit setting-prices-monthly-edit-btn" data-user-id="' + uid + '" title="\u0418\u0437\u043C\u0435\u043D\u0438\u0442\u044C \u0441\u0442\u0430\u0442\u0443\u0441 \u0438 \u0441\u0443\u043C\u043C\u0443"><i class="fa fa-edit" aria-hidden="true"></i></button>';
      }
      const isEditing = uid && editingMonthlyUserId !== null && String(editingMonthlyUserId) === uid;
      let statusCellHtml = "";
      if (isEditing) {
        const selVal = eff ? "1" : "0";
        statusCellHtml = '<div class="user-price-status-edit setting-prices-monthly-edit-panel"><div class="d-flex flex-nowrap align-items-center gap-1 justify-content-end"><select class="form-select form-select-sm user-manual-paid-select setting-prices-monthly-paid-select" data-initial="' + selVal + '" aria-label="\u0421\u0442\u0430\u0442\u0443\u0441 \u043E\u043F\u043B\u0430\u0442\u044B"><option value="1"' + (eff ? " selected" : "") + '>\u041E\u043F\u043B\u0430\u0447\u0435\u043D\u043E</option><option value="0"' + (!eff ? " selected" : "") + '>\u041D\u0435 \u043E\u043F\u043B\u0430\u0447\u0435\u043D\u043E</option></select><button type="button" class="btn btn-sm btn-danger user-price-edit-cancel d-inline-flex align-items-center justify-content-center px-2" title="\u041E\u0442\u043C\u0435\u043D\u0430" aria-label="\u041E\u0442\u043C\u0435\u043D\u0430"><i class="fa fa-times" aria-hidden="true"></i></button></div><div class="manual-paid-error small text-danger mt-1" style="display:none"></div></div>';
      } else {
        const paidLabel = eff ? "\u041E\u043F\u043B\u0430\u0447\u0435\u043D\u043E" : "\u041D\u0435 \u043E\u043F\u043B\u0430\u0447\u0435\u043D\u043E";
        const paidIconHtml = eff ? '<i class="fa fa-check green-check setting-prices-monthly-paid-icon" tabindex="0" data-kids-tooltip-hint="1" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="ulp-assignment-paid-tooltip" title="\u041E\u043F\u043B\u0430\u0447\u0435\u043D\u043E" aria-label="\u041E\u043F\u043B\u0430\u0447\u0435\u043D\u043E"></i>' : '<span class="setting-prices-monthly-paid-empty" aria-hidden="true"></span>';
        statusCellHtml = '<div class="user-price-status-view setting-prices-monthly-status-view d-flex align-items-center flex-nowrap gap-1"><div class="user-price-badge-wrap position-relative setting-prices-monthly-badge-wrap" aria-label="' + paidLabel + '">' + paidIconHtml + infoIcon + '</div><div class="setting-prices-monthly-edit-wrap">' + pencilHtml + "</div></div>";
      }
      const packageId = up.lesson_package_id != null ? up.lesson_package_id : "";
      const packageSelectDisabled = eff ? "disabled" : "";
      let priceInputDisabled = "disabled";
      if (isEditing) {
        priceInputDisabled = "";
      } else if (!canManage && !eff) {
        priceInputDisabled = "";
      }
      const nameHtml = window.KidsCrmTooltip && typeof window.KidsCrmTooltip.renderText === "function" ? window.KidsCrmTooltip.renderText(userNameFormatted) : '<span class="setting-prices-monthly-name-text text-truncate" title="' + escapeAttr(userNameFormatted) + '">' + escapeHtml(userNameFormatted) + "</span>";
      const userBlock = `
                        <div class="setting-prices-user-card mb-2 pb-2 border-bottom" data-user-id="${uid}">
                            <div class="setting-prices-monthly-row d-flex align-items-center gap-1 gap-md-2 flex-nowrap w-100 min-w-0">
                                <div class="setting-prices-monthly-name-col min-w-0">
                                    <span id="${uid}" class="user-name setting-prices-monthly-name-host d-block min-w-0 w-100">${nameHtml}</span>
                                </div>
                                <div class="setting-prices-monthly-package flex-shrink-0">
                                    <select class="form-select form-select-sm setting-prices-monthly-package-select"
                                        ${packageSelectDisabled}
                                        aria-label="\u0410\u0431\u043E\u043D\u0435\u043C\u0435\u043D\u0442">
                                        ${buildPackageSelectOptions(packageId)}
                                    </select>
                                </div>
                                <div class="setting-prices-monthly-price flex-shrink-0">
                                    <input type="number" step="0.01" min="0"
                                        class="form-control form-control-sm setting-prices-monthly-price-input"
                                        value="${escapeAttr(formatPriceValue(up.price))}"
                                        ${priceInputDisabled}
                                        aria-label="\u0426\u0435\u043D\u0430">
                                </div>
                                <div class="setting-prices-monthly-status flex-shrink-0 min-w-0">
                                    ${statusCellHtml}
                                </div>
                            </div>
                        </div>`;
      rightBar.append(userBlock);
    }
    document.querySelector("#right_bar .btn-setting-prices").removeAttribute("disabled");
    requestAnimationFrame(function() {
      if (!rightBarEl || !window.KidsCrmTooltip) {
        return;
      }
      window.KidsCrmTooltip.init(rightBarEl, { scopes: ["text", "manualPaid", "hint"] });
    });
  }
  $(document).on("change", "#right_bar .wrap-users .setting-prices-monthly-package-select", function() {
    const $select = $(this);
    const $card = $select.closest(".setting-prices-user-card");
    const uid = $card.attr("data-user-id");
    const pkg = findLessonPackage($select.val());
    const $priceInput = $card.find(".setting-prices-monthly-price-input");
    if (pkg) {
      $priceInput.val(formatPriceValue(pkg.price));
    }
    if (uid) {
      const idx = usersPrice.findIndex(function(u) {
        return String(u.user_id) === String(uid);
      });
      if (idx >= 0) {
        const pkgVal = $select.val();
        usersPrice[idx].lesson_package_id = pkgVal !== "" ? parseInt(pkgVal, 10) : null;
        usersPrice[idx].price = $priceInput.val();
      }
    }
    const inEditMode = uid && editingMonthlyUserId !== null && String(editingMonthlyUserId) === String(uid);
    if (!inEditMode && lastCanManageManualPaid) {
      $priceInput.prop("disabled", true);
    }
  });
  $(document).on("input change", "#right_bar .wrap-users .setting-prices-monthly-price-input", function() {
    const $input = $(this);
    const uid = $input.closest(".setting-prices-user-card").attr("data-user-id");
    if (!uid) {
      return;
    }
    const idx = usersPrice.findIndex(function(u) {
      return String(u.user_id) === String(uid);
    });
    if (idx >= 0) {
      usersPrice[idx].price = $input.val();
    }
  });
  $(document).on("click", "#right_bar .wrap-users .user-price-manual-edit", function(e) {
    e.preventDefault();
    e.stopPropagation();
    const uid = $(this).attr("data-user-id");
    if (!uid) {
      return;
    }
    syncUsersPriceFromDom();
    if (String(editingMonthlyUserId) === String(uid)) {
      editingMonthlyUserId = null;
      renderUsersRightColumn(lastUsersTeam, usersPrice, lastCanManageManualPaid);
      return;
    }
    editingMonthlyUserId = uid;
    renderUsersRightColumn(lastUsersTeam, usersPrice, lastCanManageManualPaid);
  });
  $(document).on("click", "#right_bar .wrap-users .user-price-edit-cancel", function(e) {
    e.preventDefault();
    syncUsersPriceFromDom();
    editingMonthlyUserId = null;
    renderUsersRightColumn(lastUsersTeam, usersPrice, lastCanManageManualPaid);
  });
  $(document).on("change", "#right_bar .wrap-users .user-manual-paid-select", function() {
    const $sel = $(this);
    const $card = $sel.closest(".setting-prices-user-card");
    const userId = $card.attr("data-user-id");
    const val = $sel.val();
    const initial = $sel.data("initial");
    if (String(val) === String(initial)) {
      return;
    }
    const selectedDate = getSelectedMonthLabel();
    const mode = val === "1" ? "paid" : "unpaid";
    const labelWant = val === "1" ? "\u043E\u043F\u043B\u0430\u0447\u0435\u043D\u043E" : "\u043D\u0435 \u043E\u043F\u043B\u0430\u0447\u0435\u043D\u043E";
    const errBox = $card.find(".manual-paid-error")[0];
    $sel.val(initial);
    if (typeof window.showManualPaidCommentModal !== "function") {
      console.error("showManualPaidCommentModal not available");
      return;
    }
    window.showManualPaidCommentModal(
      "\u041F\u043E\u0434\u0442\u0432\u0435\u0440\u0436\u0434\u0435\u043D\u0438\u0435",
      "\u0411\u0443\u0434\u0435\u0442 \u0443\u0441\u0442\u0430\u043D\u043E\u0432\u043B\u0435\u043D \u0441\u0442\u0430\u0442\u0443\u0441: \xAB" + labelWant + "\xBB. \u0423\u043A\u0430\u0436\u0438\u0442\u0435 \u043A\u043E\u043C\u043C\u0435\u043D\u0442\u0430\u0440\u0438\u0439.",
      function(comment) {
        if (!lastTeamId) {
          if (errorEl) {
            errorEl.style.display = "block";
            errorEl.textContent = "\u041D\u0435 \u0432\u044B\u0431\u0440\u0430\u043D\u0430 \u0433\u0440\u0443\u043F\u043F\u0430.";
          }
          return;
        }
        postManualPaid(userId, lastTeamId, selectedDate, mode, comment, errBox);
      }
    );
  });
  $(document).on("change", "#left_bar .setting-prices-team-package-select", function() {
    const rowEl = this.closest(".wrap-team");
    syncTeamRowPackageUi(rowEl);
    syncSetPriceAllTeamsButton();
    refreshTeamOkTooltips();
  });
  document.querySelectorAll("#left_bar .wrap-team").forEach(function(rowEl) {
    rowEl.addEventListener("click", function(e) {
      if (e.target.closest("select, input, button, .ok, label, a")) {
        return;
      }
      openTeamDetail(rowEl);
    });
  });
  const okButtons = document.querySelectorAll("#left_bar .ok");
  for (let i = 0; i < okButtons.length; i++) {
    let button = okButtons[i];
    button.addEventListener("click", function(e) {
      e.stopPropagation();
      const parentDiv = this.closest(".wrap-team");
      if (!parentDiv || isTeamOkDisabled(button)) {
        return;
      }
      const packageSelect = parentDiv.querySelector(".setting-prices-team-package-select");
      const packageId = packageSelect ? packageSelect.value : "";
      if (!packageId) {
        return;
      }
      const selectedDate = getSelectedMonthLabel();
      const priceEl = parentDiv.querySelector(".setting-prices-team-price-value");
      if (priceEl) {
        priceEl.classList.remove("animated-input");
      }
      showConfirmDeleteModal(
        "\u041F\u043E\u0434\u0442\u0432\u0435\u0440\u0434\u0438\u0442\u0435 \u0434\u0435\u0439\u0441\u0442\u0432\u0438\u0435",
        "\u0412\u044B \u0434\u0435\u0439\u0441\u0442\u0432\u0438\u0442\u0435\u043B\u044C\u043D\u043E \u0445\u043E\u0442\u0438\u0442\u0435 \u0443\u0441\u0442\u0430\u043D\u043E\u0432\u0438\u0442\u044C \u0430\u0431\u043E\u043D\u0435\u043C\u0435\u043D\u0442 \u0434\u043B\u044F \u044D\u0442\u043E\u0439 \u0433\u0440\u0443\u043F\u043F\u044B?",
        function() {
          const csrf = $('meta[name="csrf-token"]').attr("content");
          $.ajax({
            url: "/admin/setting-prices/set-team-price",
            method: "POST",
            contentType: "application/json",
            dataType: "json",
            headers: {
              "X-CSRF-TOKEN": csrf,
              "Accept": "application/json"
            },
            data: JSON.stringify({
              teamId: parentDiv.id,
              lesson_package_id: parseInt(packageId, 10),
              selectedDate
            }),
            success: function(response) {
              if (response.success) {
                if (typeof response.teamPrice !== "undefined") {
                  parentDiv.setAttribute("data-legacy-price", String(response.teamPrice));
                  if (priceEl) {
                    priceEl.textContent = formatPriceValue(response.teamPrice);
                    priceEl.setAttribute("data-price", String(response.teamPrice));
                    priceEl.classList.add("animated-input");
                  }
                }
                if (String(lastTeamId) === String(parentDiv.id)) {
                  loadTeamUsersRightColumn(parentDiv.id);
                }
              }
            },
            error: function(xhr) {
              let msg = "\u041D\u0435 \u0443\u0434\u0430\u043B\u043E\u0441\u044C \u0443\u0441\u0442\u0430\u043D\u043E\u0432\u0438\u0442\u044C \u0430\u0431\u043E\u043D\u0435\u043C\u0435\u043D\u0442 \u0434\u043B\u044F \u0433\u0440\u0443\u043F\u043F\u044B.";
              if (xhr.responseJSON) {
                if (xhr.responseJSON.message) {
                  msg = xhr.responseJSON.message;
                }
                const errs = xhr.responseJSON.errors;
                if (errs) {
                  const firstKey = Object.keys(errs)[0];
                  if (firstKey && errs[firstKey] && errs[firstKey][0]) {
                    msg = errs[firstKey][0];
                  }
                }
              }
              if (typeof showErrorModal === "function") {
                showErrorModal("\u041E\u0448\u0438\u0431\u043A\u0430", msg);
              } else {
                alert(msg);
              }
            }
          });
        }
      );
    });
  }
  $(".set-price-all-teams").on("click", function() {
    if (this.disabled) {
      return;
    }
    showConfirmDeleteModal(
      "\u0423\u0441\u0442\u0430\u043D\u043E\u0432\u043A\u0430 \u0442\u0430\u0440\u0438\u0444\u043E\u0432 \u0432\u0441\u0435\u043C \u0433\u0440\u0443\u043F\u043F\u0430\u043C",
      "\u0412\u044B \u0443\u0432\u0435\u0440\u0435\u043D\u044B, \u0447\u0442\u043E \u0445\u043E\u0442\u0438\u0442\u0435 \u043F\u0440\u0438\u043C\u0435\u043D\u0438\u0442\u044C \u0438\u0437\u043C\u0435\u043D\u0435\u043D\u0438\u044F?",
      function() {
        const selectedDate = getSelectedMonthLabel();
        const applyBtn = document.querySelector("#set-price-all-teams");
        if (applyBtn) {
          applyBtn.setAttribute("disabled", "disabled");
        }
        let teamsData = [];
        document.querySelectorAll("#left_bar .wrap-team").forEach(function(teamElement) {
          let teamId = teamElement.id;
          let packageSelect = teamElement.querySelector(".setting-prices-team-package-select");
          let pkgVal = packageSelect ? packageSelect.value : "";
          if (!pkgVal) {
            return;
          }
          teamsData.push({
            teamId,
            lesson_package_id: parseInt(pkgVal, 10)
          });
        });
        if (teamsData.length === 0) {
          syncSetPriceAllTeamsButton();
          return;
        }
        const csrf = $('meta[name="csrf-token"]').attr("content");
        $.ajax({
          url: "/admin/setting-prices/set-price-all-teams",
          method: "POST",
          contentType: "application/json",
          headers: {
            "X-CSRF-TOKEN": csrf,
            "Accept": "application/json"
          },
          data: JSON.stringify({
            selectedDate,
            teamsData
          }),
          success: function() {
            showSuccessModal("\u0423\u0441\u0442\u0430\u043D\u043E\u0432\u043A\u0430 \u0442\u0430\u0440\u0438\u0444\u043E\u0432 \u0432\u0441\u0435\u043C \u0433\u0440\u0443\u043F\u043F\u0430\u043C", "\u0422\u0430\u0440\u0438\u0444\u044B \u0433\u0440\u0443\u043F\u043F\u0430\u043C \u0443\u0441\u043F\u0435\u0448\u043D\u043E \u043E\u0431\u043D\u043E\u0432\u043B\u0435\u043D\u044B.", 1);
          },
          error: function(xhr) {
            syncSetPriceAllTeamsButton();
            let msg = "\u041D\u0435 \u0443\u0434\u0430\u043B\u043E\u0441\u044C \u043F\u0440\u0438\u043C\u0435\u043D\u0438\u0442\u044C \u0442\u0430\u0440\u0438\u0444\u044B.";
            if (xhr.responseJSON) {
              if (xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
              }
              const errs = xhr.responseJSON.errors;
              if (errs) {
                const firstKey = Object.keys(errs)[0];
                if (firstKey && errs[firstKey] && errs[firstKey][0]) {
                  msg = errs[firstKey][0];
                }
              }
            }
            if (typeof showErrorModal === "function") {
              showErrorModal("\u041E\u0448\u0438\u0431\u043A\u0430", msg);
            } else {
              $("#errorModal").modal("show");
            }
          }
        });
      }
    );
  });
  $("#set-price-all-users").on("click", function() {
    showConfirmDeleteModal(
      "\u0423\u0441\u0442\u0430\u043D\u043E\u0432\u043A\u0430 \u0446\u0435\u043D \u0432 \u043E\u0434\u043D\u043E\u0439 \u0433\u0440\u0443\u043F\u043F\u0435",
      "\u0412\u044B \u0443\u0432\u0435\u0440\u0435\u043D\u044B, \u0447\u0442\u043E \u0445\u043E\u0442\u0438\u0442\u0435 \u043F\u0440\u0438\u043C\u0435\u043D\u0438\u0442\u044C \u0438\u0437\u043C\u0435\u043D\u0435\u043D\u0438\u044F?",
      function() {
        const selectedDate = getSelectedMonthLabel();
        let updateUsersPrice = function(usersPriceLocal) {
          const userRows = document.querySelectorAll(".wrap-users .setting-prices-user-card");
          for (let i = 0; i < usersPriceLocal.length; i++) {
            for (let j = 0; j < userRows.length; j++) {
              let userId = userRows[j].getAttribute("data-user-id");
              let priceInput = userRows[j].querySelector(".setting-prices-monthly-price-input");
              let packageSelect = userRows[j].querySelector(".setting-prices-monthly-package-select");
              let price = priceInput ? priceInput.value : null;
              if (price !== null && String(usersPriceLocal[i].user_id) === String(userId)) {
                usersPriceLocal[i].price = price;
                const pkgVal = packageSelect ? packageSelect.value : "";
                usersPriceLocal[i].lesson_package_id = pkgVal !== "" ? parseInt(pkgVal, 10) : null;
              }
            }
          }
          return usersPriceLocal;
        };
        usersPrice = updateUsersPrice(usersPrice);
        const csrf = $('meta[name="csrf-token"]').attr("content");
        $.ajax({
          url: "/admin/setting-prices/set-price-all-users",
          method: "POST",
          contentType: "application/json",
          dataType: "json",
          headers: {
            "X-CSRF-TOKEN": csrf,
            "Accept": "application/json"
          },
          data: JSON.stringify({
            selectedDate,
            teamId: lastTeamId,
            usersPrice
          }),
          success: function(response) {
            usersPrice = response.usersPrice;
            if (Array.isArray(response.lessonPackages)) {
              lastLessonPackages = response.lessonPackages;
            }
            document.querySelector("#set-price-all-users").removeAttribute("disabled");
            showSuccessModal("\u0423\u0441\u0442\u0430\u043D\u043E\u0432\u043A\u0430 \u0446\u0435\u043D \u0432 \u043E\u0434\u043D\u043E\u0439 \u0433\u0440\u0443\u043F\u043F\u0435", "\u0426\u0435\u043D\u044B \u0443\u0447\u0435\u043D\u0438\u043A\u0430\u043C \u0432 \u0432\u044B\u0431\u0440\u0430\u043D\u043D\u043E\u0439 \u0433\u0440\u0443\u043F\u043F\u0435 \u0443\u0441\u043F\u0435\u0448\u043D\u043E \u043E\u0431\u043D\u043E\u0432\u043B\u0435\u043D\u044B.");
            editingMonthlyUserId = null;
            const wrap = document.querySelector("#right_bar .wrap-users");
            let usersTeam = [];
            try {
              const json = wrap && wrap.getAttribute("data-users-team-json");
              usersTeam = json ? JSON.parse(json) : [];
            } catch (e) {
              usersTeam = [];
            }
            renderUsersRightColumn(usersTeam, usersPrice, lastCanManageManualPaid);
          },
          error: function(xhr, status, error) {
            console.log("Error:", error);
            let msg = "\u041D\u0435 \u0443\u0434\u0430\u043B\u043E\u0441\u044C \u0441\u043E\u0445\u0440\u0430\u043D\u0438\u0442\u044C \u0446\u0435\u043D\u044B.";
            if (xhr.responseJSON) {
              if (xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
              }
              const errs = xhr.responseJSON.errors;
              if (errs) {
                const firstKey = Object.keys(errs)[0];
                if (firstKey && errs[firstKey] && errs[firstKey][0]) {
                  msg = errs[firstKey][0];
                }
              }
            }
            if (typeof showErrorModal === "function") {
              showErrorModal("\u041E\u0448\u0438\u0431\u043A\u0430", msg);
            } else {
              alert(msg);
            }
          }
        });
      }
    );
  });
});
