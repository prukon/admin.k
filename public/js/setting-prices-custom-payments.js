// resources/js/setting-prices-custom-payments.js
(function() {
  function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
  }
  function setCreateFieldErrors(errors) {
    document.querySelectorAll(".custom-payment-field-error").forEach(function(el) {
      el.style.display = "none";
      el.textContent = "";
    });
    if (!errors)
      return;
    Object.keys(errors).forEach(function(field) {
      var msg = errors[field] && errors[field][0] ? errors[field][0] : null;
      if (!msg)
        return;
      var el = document.querySelector('.custom-payment-field-error[data-field="' + field + '"]');
      if (!el)
        return;
      el.textContent = msg;
      el.style.display = "block";
    });
  }
  function setEditFieldErrors(errors) {
    document.querySelectorAll(".custom-payment-edit-field-error").forEach(function(el) {
      el.style.display = "none";
      el.textContent = "";
    });
    if (!errors)
      return;
    Object.keys(errors).forEach(function(field) {
      var msg = errors[field] && errors[field][0] ? errors[field][0] : null;
      if (!msg)
        return;
      var el = document.querySelector('.custom-payment-edit-field-error[data-field="' + field + '"]');
      if (!el)
        return;
      el.textContent = msg;
      el.style.display = "block";
    });
  }
  function toast(msg, isError) {
    if (typeof window.bootstrap === "undefined" || !bootstrap.Toast) {
      alert(msg || (isError ? "\u041E\u0448\u0438\u0431\u043A\u0430" : "OK"));
      return;
    }
    var wrapper = document.querySelector(".position-fixed.bottom-0.end-0.p-3");
    if (!wrapper) {
      alert(msg || (isError ? "\u041E\u0448\u0438\u0431\u043A\u0430" : "OK"));
      return;
    }
    var toastEl = document.getElementById("priceToast");
    var bodyEl = document.getElementById("priceToastBody");
    if (!toastEl || !bodyEl) {
      alert(msg || (isError ? "\u041E\u0448\u0438\u0431\u043A\u0430" : "OK"));
      return;
    }
    bodyEl.textContent = msg || (isError ? "\u041E\u0448\u0438\u0431\u043A\u0430" : "OK");
    toastEl.classList.remove("bg-success", "bg-danger");
    toastEl.classList.add(isError ? "bg-danger" : "bg-success");
    new bootstrap.Toast(toastEl).show();
  }
  function syncEditStatusCommentVisibility() {
    var initial = document.getElementById("custom-payment-edit-initial-is-paid");
    var select = document.getElementById("custom-payment-edit-is-paid");
    var wrap = document.getElementById("custom-payment-edit-status-comment-wrap");
    var comment = document.getElementById("custom-payment-edit-status-comment");
    if (!initial || !select || !wrap)
      return;
    var changed = String(select.value) !== String(initial.value);
    wrap.style.display = changed ? "" : "none";
    if (!changed && comment) {
      comment.value = "";
    }
  }
  function openEditModal(row) {
    var idEl = document.getElementById("custom-payment-edit-id");
    var amountEl = document.getElementById("custom-payment-edit-amount");
    var noteEl = document.getElementById("custom-payment-edit-note");
    var paidEl = document.getElementById("custom-payment-edit-is-paid");
    var initialPaidEl = document.getElementById("custom-payment-edit-initial-is-paid");
    var deleteBtn = document.getElementById("custom-payment-edit-delete");
    var commentEl = document.getElementById("custom-payment-edit-status-comment");
    if (!idEl || !amountEl || !paidEl || !initialPaidEl)
      return;
    setEditFieldErrors(null);
    var paid = row.effective_is_paid === true || row.effective_is_paid === 1 || row.effective_is_paid === "1";
    idEl.value = String(row.id);
    amountEl.value = row.amount != null && row.amount !== "" ? String(Math.round(Number(row.amount))) : "";
    amountEl.disabled = paid;
    if (noteEl) {
      noteEl.value = row.note != null ? String(row.note) : "";
    }
    paidEl.value = paid ? "1" : "0";
    initialPaidEl.value = paid ? "1" : "0";
    if (commentEl) {
      commentEl.value = "";
    }
    if (deleteBtn) {
      deleteBtn.style.display = paid ? "none" : "";
    }
    syncEditStatusCommentVisibility();
    var modalEl = document.getElementById("customPaymentEditModal");
    if (modalEl && window.bootstrap && bootstrap.Modal) {
      if (typeof window.showModalQueued === "function") {
        window.showModalQueued("customPaymentEditModal", { backdrop: "static", keyboard: false });
      } else {
        bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: "static", keyboard: false }).show();
      }
    }
  }
  document.addEventListener("DOMContentLoaded", function() {
    if (window.$) {
      $.ajaxSetup({
        headers: {
          "X-CSRF-TOKEN": csrf(),
          "Accept": "application/json"
        }
      });
    }
    var dtApi = null;
    if (window.$ && window.KidsCrmDataTable && $.fn && $.fn.DataTable) {
      dtApi = KidsCrmDataTable.create("#custom-payments-table", {
        dataTable: {
          ajax: {
            url: "/admin/setting-prices/custom-payments/data",
            type: "GET"
          },
          order: [[0, "desc"]],
          language: window.__kidsDatatableRu || {
            processing: "\u041E\u0431\u0440\u0430\u0431\u043E\u0442\u043A\u0430...",
            search: "",
            searchPlaceholder: "\u041F\u043E\u0438\u0441\u043A...",
            lengthMenu: "\u041F\u043E\u043A\u0430\u0437\u0430\u0442\u044C _MENU_",
            info: "\u0421 _START_ \u0434\u043E _END_ \u0438\u0437 _TOTAL_ \u0437\u0430\u043F\u0438\u0441\u0435\u0439",
            infoEmpty: "\u0421 0 \u0434\u043E 0 \u0438\u0437 0 \u0437\u0430\u043F\u0438\u0441\u0435\u0439",
            infoFiltered: "(\u043E\u0442\u0444\u0438\u043B\u044C\u0442\u0440\u043E\u0432\u0430\u043D\u043E \u0438\u0437 _MAX_ \u0437\u0430\u043F\u0438\u0441\u0435\u0439)",
            loadingRecords: "\u0417\u0430\u0433\u0440\u0443\u0437\u043A\u0430 \u0437\u0430\u043F\u0438\u0441\u0435\u0439...",
            zeroRecords: "\u0417\u0430\u043F\u0438\u0441\u0438 \u043E\u0442\u0441\u0443\u0442\u0441\u0442\u0432\u0443\u044E\u0442.",
            emptyTable: "\u0412 \u0442\u0430\u0431\u043B\u0438\u0446\u0435 \u043E\u0442\u0441\u0443\u0442\u0441\u0442\u0432\u0443\u044E\u0442 \u0434\u0430\u043D\u043D\u044B\u0435",
            paginate: { first: "", previous: "", next: "", last: "" },
            aria: {
              sortAscending: ": \u0430\u043A\u0442\u0438\u0432\u0438\u0440\u043E\u0432\u0430\u0442\u044C \u0434\u043B\u044F \u0441\u043E\u0440\u0442\u0438\u0440\u043E\u0432\u043A\u0438 \u0441\u0442\u043E\u043B\u0431\u0446\u0430 \u043F\u043E \u0432\u043E\u0437\u0440\u0430\u0441\u0442\u0430\u043D\u0438\u044E",
              sortDescending: ": \u0430\u043A\u0442\u0438\u0432\u0438\u0440\u043E\u0432\u0430\u0442\u044C \u0434\u043B\u044F \u0441\u043E\u0440\u0442\u0438\u0440\u043E\u0432\u043A\u0438 \u0441\u0442\u043E\u043B\u0431\u0446\u0430 \u043F\u043E \u0443\u0431\u044B\u0432\u0430\u043D\u0438\u044E"
            }
          }
        },
        columns: [
          { key: "id", type: "id" },
          { key: "user_name", type: "text", data: "user_name" },
          { key: "team_label", type: "text", data: "team_label", orderable: false },
          { key: "amount", type: "money", data: "amount" },
          {
            key: "note",
            type: "text",
            data: "note",
            orderable: false,
            render: function(data, type) {
              if (type !== "display") {
                return data || "";
              }
              if (data == null || data === "") {
                return '<span class="text-muted">\u2014</span>';
              }
              return window.KidsCrmTooltip.renderText(data);
            }
          },
          {
            key: "status",
            type: "badge",
            data: "status_label",
            name: "status",
            className: "dt-col-badge text-center",
            orderable: false,
            searchable: false,
            badgeKey: "effective_is_paid",
            render: function(value, type, row) {
              if (type !== "display") {
                return value || "";
              }
              var paid = !!row.effective_is_paid;
              var badgeClass = paid ? "bg-success" : "bg-secondary";
              var badgeText = value || (paid ? "\u041E\u043F\u043B\u0430\u0447\u0435\u043D\u043E" : "\u041D\u0435 \u043E\u043F\u043B\u0430\u0447\u0435\u043D\u043E");
              var infoIcon = "";
              if (row.is_manual_paid !== null && row.is_manual_paid !== void 0) {
                var note = row.manual_paid_note != null ? String(row.manual_paid_note).trim() : "";
                var hintTitle = note !== "" ? note : "\u041A\u043E\u043C\u043C\u0435\u043D\u0442\u0430\u0440\u0438\u0439 \u043A \u0440\u0443\u0447\u043D\u043E\u043C\u0443 \u0438\u0437\u043C\u0435\u043D\u0435\u043D\u0438\u044E \u043D\u0435 \u0437\u0430\u043F\u043E\u043B\u043D\u0435\u043D.";
                infoIcon = '<i class="fa fa-info-circle user-manual-info-icon" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="ulp-assignment-paid-tooltip" title="' + hintTitle.replace(/"/g, "&quot;") + '" aria-label="\u041A\u043E\u043C\u043C\u0435\u043D\u0442\u0430\u0440\u0438\u0439 \u043A \u0440\u0443\u0447\u043D\u043E\u0439 \u043E\u0442\u043C\u0435\u0442\u043A\u0435 \u043E\u043F\u043B\u0430\u0442\u044B"></i>';
              }
              return '<div class="setting-prices-monthly-status-view d-flex align-items-center flex-nowrap gap-1"><div class="setting-prices-monthly-badge-wrap position-relative"><span class="badge ' + badgeClass + '">' + badgeText + "</span>" + infoIcon + "</div></div>";
            }
          },
          {
            key: "actions",
            type: "actions",
            className: "dt-col-actions text-nowrap text-end",
            render: function(data, type, row) {
              if (type !== "display" || !window.__customPaymentsCanManualPaid) {
                return "";
              }
              return '<button type="button" class="btn btn-sm btn-outline-primary" data-custom-payment-action="edit" data-id="' + String(row.id) + '">\u0420\u0435\u0434\u0430\u043A\u0442\u0438\u0440\u043E\u0432\u0430\u0442\u044C</button>';
            }
          }
        ]
      });
    }
    var paidSelect = document.getElementById("custom-payment-edit-is-paid");
    if (paidSelect) {
      paidSelect.addEventListener("change", syncEditStatusCommentVisibility);
    }
    var form = document.getElementById("custom-payment-create-form");
    if (form) {
      form.addEventListener("submit", function(e) {
        e.preventDefault();
        setCreateFieldErrors(null);
        var payload = {
          user_id: form.querySelector('[name="user_id"]').value,
          team_id: form.querySelector('[name="team_id"]').value,
          amount: form.querySelector('[name="amount"]').value,
          note: form.querySelector('[name="note"]').value
        };
        var btn = document.getElementById("custom-payment-create-submit");
        if (btn)
          btn.disabled = true;
        fetch("/admin/setting-prices/custom-payments", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "Accept": "application/json",
            "X-CSRF-TOKEN": csrf()
          },
          body: JSON.stringify(payload)
        }).then(async function(res) {
          var data = null;
          try {
            data = await res.json();
          } catch (err) {
          }
          if (!res.ok) {
            if (data && data.errors) {
              setCreateFieldErrors(data.errors);
            }
            throw new Error(data && data.message ? data.message : "\u041D\u0435 \u0443\u0434\u0430\u043B\u043E\u0441\u044C \u0441\u043E\u0437\u0434\u0430\u0442\u044C \u0434\u043E\u043F\u043E\u043B\u043D\u0438\u0442\u0435\u043B\u044C\u043D\u044B\u0439 \u043F\u043B\u0430\u0442\u0435\u0436.");
          }
          return data;
        }).then(function() {
          form.reset();
          if (window.$ && $("#custom-payment-user-id").length) {
            $("#custom-payment-user-id").val(null).trigger("change");
          }
          if (window.$ && $("#custom-payment-team-id").length) {
            $("#custom-payment-team-id").val(null).trigger("change").prop("disabled", true);
          }
          var modalEl = document.getElementById("customPaymentCreateModal");
          if (modalEl && window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
          }
          if (dtApi) {
            dtApi.reload({ keepPage: true });
          }
          if (typeof window.showToast === "function") {
            window.showToast("Дополнительный платеж успешно создан.", "success");
          } else {
            toast("Дополнительный платеж успешно создан.", false);
          }
        }).catch(function(err) {
          toast(err && err.message ? err.message : "\u041E\u0448\u0438\u0431\u043A\u0430", true);
        }).finally(function() {
          if (btn)
            btn.disabled = false;
        });
      });
    }
    var editForm = document.getElementById("custom-payment-edit-form");
    if (editForm) {
      editForm.addEventListener("submit", function(e) {
        e.preventDefault();
        setEditFieldErrors(null);
        var id = document.getElementById("custom-payment-edit-id")?.value;
        if (!id)
          return;
        var amountEl = document.getElementById("custom-payment-edit-amount");
        var initialPaid = document.getElementById("custom-payment-edit-initial-is-paid")?.value === "1";
        var isPaid = document.getElementById("custom-payment-edit-is-paid")?.value === "1";
        var payload = {
          note: document.getElementById("custom-payment-edit-note")?.value || "",
          is_paid: isPaid
        };
        if (!initialPaid && amountEl && !amountEl.disabled) {
          payload.amount = amountEl.value;
        }
        if (isPaid !== initialPaid) {
          payload.status_comment = document.getElementById("custom-payment-edit-status-comment")?.value || "";
        }
        var btn = document.getElementById("custom-payment-edit-submit");
        if (btn)
          btn.disabled = true;
        fetch("/admin/setting-prices/custom-payments/" + id, {
          method: "PUT",
          headers: {
            "Content-Type": "application/json",
            "Accept": "application/json",
            "X-CSRF-TOKEN": csrf()
          },
          body: JSON.stringify(payload)
        }).then(async function(res) {
          var data = null;
          try {
            data = await res.json();
          } catch (err) {
          }
          if (!res.ok) {
            if (data && data.errors) {
              setEditFieldErrors(data.errors);
            }
            throw new Error(data && data.message ? data.message : "\u041D\u0435 \u0443\u0434\u0430\u043B\u043E\u0441\u044C \u0441\u043E\u0445\u0440\u0430\u043D\u0438\u0442\u044C \u0438\u0437\u043C\u0435\u043D\u0435\u043D\u0438\u044F.");
          }
          return data;
        }).then(function() {
          var modalEl = document.getElementById("customPaymentEditModal");
          if (modalEl && window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
          }
          if (dtApi) {
            dtApi.reload({ keepPage: true });
          }
          toast("\u0418\u0437\u043C\u0435\u043D\u0435\u043D\u0438\u044F \u0441\u043E\u0445\u0440\u0430\u043D\u0435\u043D\u044B.", false);
        }).catch(function(err) {
          toast(err && err.message ? err.message : "\u041E\u0448\u0438\u0431\u043A\u0430", true);
        }).finally(function() {
          if (btn)
            btn.disabled = false;
        });
      });
    }
    var deleteBtn = document.getElementById("custom-payment-edit-delete");
    if (deleteBtn) {
      deleteBtn.addEventListener("click", function() {
        var id = document.getElementById("custom-payment-edit-id")?.value;
        if (!id)
          return;
        if (typeof window.showConfirmDeleteModal !== "function") {
          toast("\u041D\u0435 \u0437\u0430\u0433\u0440\u0443\u0436\u0435\u043D\u0430 \u0444\u043E\u0440\u043C\u0430 \u043F\u043E\u0434\u0442\u0432\u0435\u0440\u0436\u0434\u0435\u043D\u0438\u044F. \u041E\u0431\u043D\u043E\u0432\u0438\u0442\u0435 \u0441\u0442\u0440\u0430\u043D\u0438\u0446\u0443.", true);
          return;
        }
        window.showConfirmDeleteModal(
          "\u0423\u0434\u0430\u043B\u0435\u043D\u0438\u0435 \u0434\u043E\u043F\u043E\u043B\u043D\u0438\u0442\u0435\u043B\u044C\u043D\u043E\u0433\u043E \u043F\u043B\u0430\u0442\u0435\u0436\u0430",
          "\u0412\u044B \u0443\u0432\u0435\u0440\u0435\u043D\u044B, \u0447\u0442\u043E \u0445\u043E\u0442\u0438\u0442\u0435 \u0443\u0434\u0430\u043B\u0438\u0442\u044C \u044D\u0442\u043E\u0442 \u0434\u043E\u043F\u043E\u043B\u043D\u0438\u0442\u0435\u043B\u044C\u043D\u044B\u0439 \u043F\u043B\u0430\u0442\u0435\u0436?",
          function() {
            var confirmEl = document.getElementById("confirmDeleteModal");
            var editEl = document.getElementById("customPaymentEditModal");
            if (window.$ && confirmEl) {
              $(confirmEl).off("hidden.bs.modal.return");
            }
            fetch("/admin/setting-prices/custom-payments/" + id, {
              method: "DELETE",
              headers: {
                "Accept": "application/json",
                "X-CSRF-TOKEN": csrf()
              }
            }).then(async function(res) {
              var data = null;
              try {
                data = await res.json();
              } catch (err) {
              }
              if (!res.ok) {
                throw new Error(
                  data && data.message ? data.message : "\u041D\u0435 \u0443\u0434\u0430\u043B\u043E\u0441\u044C \u0443\u0434\u0430\u043B\u0438\u0442\u044C \u0434\u043E\u043F\u043E\u043B\u043D\u0438\u0442\u0435\u043B\u044C\u043D\u044B\u0439 \u043F\u043B\u0430\u0442\u0435\u0436."
                );
              }
              return data;
            }).then(function() {
              if (window.$ && editEl) {
                $(editEl).off("hidden.bs.modal.openNext");
              }
              if (editEl && window.bootstrap && bootstrap.Modal) {
                bootstrap.Modal.getInstance(editEl)?.hide();
              }
              if (dtApi) {
                dtApi.reload({ keepPage: true });
              }
              toast("\u0414\u043E\u043F\u043E\u043B\u043D\u0438\u0442\u0435\u043B\u044C\u043D\u044B\u0439 \u043F\u043B\u0430\u0442\u0435\u0436 \u0443\u0434\u0430\u043B\u0451\u043D.", false);
            }).catch(function(err) {
              if (window.$ && editEl) {
                $(editEl).off("hidden.bs.modal.openNext");
              }
              if (editEl && window.bootstrap && bootstrap.Modal) {
                bootstrap.Modal.getInstance(editEl)?.hide();
              }
              toast(err && err.message ? err.message : "\u041E\u0448\u0438\u0431\u043A\u0430", true);
            });
          }
        );
      });
    }
    document.addEventListener("click", function(e) {
      var btn = e.target.closest('[data-custom-payment-action="edit"]');
      if (!btn)
        return;
      if (!dtApi || !dtApi.table) {
        toast("\u0422\u0430\u0431\u043B\u0438\u0446\u0430 \u0435\u0449\u0451 \u043D\u0435 \u0437\u0430\u0433\u0440\u0443\u0436\u0435\u043D\u0430. \u041E\u0431\u043D\u043E\u0432\u0438\u0442\u0435 \u0441\u0442\u0440\u0430\u043D\u0438\u0446\u0443.", true);
        return;
      }
      var tr = btn.closest("tr");
      var rowData = tr ? dtApi.table.row(tr).data() : null;
      if (!rowData) {
        toast("\u041D\u0435 \u0443\u0434\u0430\u043B\u043E\u0441\u044C \u043D\u0430\u0439\u0442\u0438 \u0437\u0430\u043F\u0438\u0441\u044C \u0434\u043B\u044F \u0440\u0435\u0434\u0430\u043A\u0442\u0438\u0440\u043E\u0432\u0430\u043D\u0438\u044F.", true);
        return;
      }
      openEditModal(rowData);
    });
  });
})();
