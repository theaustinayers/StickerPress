(function ($) {
  "use strict";

  /* ── Helper: build size <option> list ── */
  function sizeOptions(selected) {
    var html = "";
    if (scAdmin.sizes && scAdmin.sizes.length) {
      $.each(scAdmin.sizes, function (i, s) {
        html +=
          '<option value="' +
          i +
          '"' +
          (parseInt(selected, 10) === i ? " selected" : "") +
          ">" +
          $("<span>").text(s.label).html() +
          "</option>";
      });
    }
    return html;
  }

  function materialOptions(selected) {
    var html = "";
    $.each(scAdmin.materials, function (_, m) {
      html +=
        '<option value="' +
        m +
        '"' +
        (selected === m ? " selected" : "") +
        ">" +
        m.charAt(0).toUpperCase() +
        m.slice(1) +
        "</option>";
    });
    return html;
  }

  function finishOptions(selected) {
    var html = "";
    $.each(scAdmin.finishes, function (_, f) {
      html +=
        '<option value="' +
        f +
        '"' +
        (selected === f ? " selected" : "") +
        ">" +
        f.charAt(0).toUpperCase() +
        f.slice(1) +
        "</option>";
    });
    return html;
  }

  function lamOptions(selected) {
    return (
      '<option value="yes"' +
      (selected === "yes" ? " selected" : "") +
      ">Yes</option>" +
      '<option value="no"' +
      (selected === "no" ? " selected" : "") +
      ">No</option>"
    );
  }

  /* ── Save Global Settings ── */
  $("#sc-global-settings-form").on("submit", function (e) {
    e.preventDefault();
    $.post(scAdmin.ajax_url, {
      action: "sc_save_global_settings",
      nonce: scAdmin.nonce,
      min_quantity: $("#sc-min-quantity").val(),
      lamination_enabled: $("#sc-lamination-enabled").is(":checked") ? "1" : "0",
    }).done(function (res) {
      alert(res.success ? "Global settings saved!" : res.data || "Error");
    });
  });

  /* ── Save Sizes ── */
  $("#sc-sizes-form").on("submit", function (e) {
    e.preventDefault();
    var sizes = [];
    $("#sc-sizes-tbody tr").each(function () {
      sizes.push({
        label: $(this).find('input[name*="[label]"]').val(),
        width: $(this).find('input[name*="[width]"]').val(),
        height: $(this).find('input[name*="[height]"]').val(),
      });
    });
    $.post(scAdmin.ajax_url, {
      action: "sc_save_sizes",
      nonce: scAdmin.nonce,
      sizes: sizes,
    }).done(function (res) {
      alert(res.success ? "Sizes saved!" : res.data || "Error");
      if (res.success) location.reload();
    });
  });

  /* ── Add Size Row ── */
  $("#sc-add-size").on("click", function () {
    var idx = $("#sc-sizes-tbody tr").length;
    var row =
      '<tr data-index="' +
      idx +
      '">' +
      '<td><input type="text" name="sizes[' +
      idx +
      '][label]" value="" placeholder=\'e.g. 3&quot; x 3&quot;\' /></td>' +
      '<td><input type="number" step="0.25" min="0.5" name="sizes[' +
      idx +
      '][width]" value="2" /></td>' +
      '<td><input type="number" step="0.25" min="0.5" name="sizes[' +
      idx +
      '][height]" value="2" /></td>' +
      '<td><button type="button" class="button sc-remove-size">Remove</button></td>' +
      "</tr>";
    $("#sc-sizes-tbody").append(row);
  });

  /* ── Remove Size ── */
  $(document).on("click", ".sc-remove-size", function () {
    $(this).closest("tr").remove();
  });

  /* ════════════════════════════════════════════
     PRICING – add / remove / edit rows
     ════════════════════════════════════════════ */

  function pricingRow(idx, sizeIdx, mat, fin, lam, price, visible) {
    var checked = visible !== false ? " checked" : "";
    return (
      '<tr data-row="' +
      idx +
      '">' +
      "<td><select>" +
      sizeOptions(sizeIdx) +
      "</select></td>" +
      "<td><select>" +
      materialOptions(mat) +
      "</select></td>" +
      "<td><select>" +
      finishOptions(fin) +
      "</select></td>" +
      "<td><select>" +
      lamOptions(lam) +
      "</select></td>" +
      '<td><input type="number" step="0.01" min="0" value="' +
      (price || "0.00") +
      '" /></td>' +
      '<td><input type="checkbox" class="sc-pricing-visible"' + checked + ' /></td>' +
      '<td><button type="button" class="button sc-remove-pricing">Remove</button></td>' +
      "</tr>"
    );
  }

  $("#sc-add-pricing").on("click", function () {
    var idx = $("#sc-pricing-tbody tr").length;
    $("#sc-pricing-tbody").append(pricingRow(idx, 0, "vinyl", "glossy", "yes", "0.00", true));
  });

  $(document).on("click", ".sc-remove-pricing", function () {
    $(this).closest("tr").remove();
  });

  /* ── Save Pricing ── */
  $("#sc-pricing-form").on("submit", function (e) {
    e.preventDefault();
    var rows = [];
    $("#sc-pricing-tbody tr").each(function () {
      var selects = $(this).find("select");
      rows.push({
        size_index: selects.eq(0).val(),
        material: selects.eq(1).val(),
        finish: selects.eq(2).val(),
        laminated: selects.eq(3).val(),
        price: $(this).find('input[type="number"]').val(),
        visible: $(this).find(".sc-pricing-visible").is(":checked") ? "1" : "0",
      });
    });
    $.post(scAdmin.ajax_url, {
      action: "sc_save_pricing",
      nonce: scAdmin.nonce,
      pricing_rows: rows,
    }).done(function (res) {
      alert(res.success ? "Pricing saved!" : res.data || "Error");
      if (res.success) location.reload();
    });
  });

  /* ════════════════════════════════════════════
     DEFAULT QUANTITY BREAKS
     ════════════════════════════════════════════ */

  $("#sc-qty-form").on("submit", function (e) {
    e.preventDefault();
    var breaks = [];
    $("#sc-qty-tbody tr").each(function () {
      breaks.push({
        min: $(this).find('input[name*="[min]"]').val(),
        max: $(this).find('input[name*="[max]"]').val(),
        multiplier: $(this).find('input[name*="[multiplier]"]').val(),
      });
    });
    $.post(scAdmin.ajax_url, {
      action: "sc_save_qty_breaks",
      nonce: scAdmin.nonce,
      qty: breaks,
    }).done(function (res) {
      alert(res.success ? "Default breaks saved!" : res.data || "Error");
    });
  });

  $("#sc-add-qty").on("click", function () {
    var idx = $("#sc-qty-tbody tr").length;
    var row =
      '<tr data-index="' +
      idx +
      '">' +
      '<td><input type="number" min="1" name="qty[' +
      idx +
      '][min]" value="1" /></td>' +
      '<td><input type="number" min="1" name="qty[' +
      idx +
      '][max]" value="100" /></td>' +
      '<td><input type="number" step="0.01" min="0" max="2" name="qty[' +
      idx +
      '][multiplier]" value="1.00" /></td>' +
      '<td><button type="button" class="button sc-remove-qty">Remove</button></td>' +
      "</tr>";
    $("#sc-qty-tbody").append(row);
  });

  $(document).on("click", ".sc-remove-qty", function () {
    $(this).closest("tr").remove();
  });

  /* ════════════════════════════════════════════
     PER-STICKER QUANTITY BREAK OVERRIDES
     ════════════════════════════════════════════ */

  var overrides = scAdmin.overrides || {};

  function renderOverrideBreaks(breaks) {
    var $tbody = $("#sc-override-breaks-tbody");
    $tbody.empty();
    if (!breaks || !breaks.length) {
      $("#sc-override-status").text(
        "No overrides set — default breaks will be used."
      );
      return;
    }
    $("#sc-override-status").text("Custom breaks configured for this sticker.");
    $.each(breaks, function (i, b) {
      $tbody.append(
        "<tr>" +
          '<td><input type="number" min="1" class="ov-min" value="' +
          b.min +
          '" /></td>' +
          '<td><input type="number" min="1" class="ov-max" value="' +
          b.max +
          '" /></td>' +
          '<td><input type="number" step="0.01" min="0" max="2" class="ov-mul" value="' +
          b.multiplier +
          '" /></td>' +
          '<td><button type="button" class="button sc-remove-override-break">Remove</button></td>' +
          "</tr>"
      );
    });
  }

  $("#sc-override-sticker-select").on("change", function () {
    var key = $(this).val();
    if (!key) {
      $("#sc-override-breaks-wrap").hide();
      return;
    }
    $("#sc-override-breaks-wrap").show();
    var breaks = overrides[key] || [];
    renderOverrideBreaks(breaks);
  });

  $("#sc-add-override-break").on("click", function () {
    $("#sc-override-breaks-tbody").append(
      "<tr>" +
        '<td><input type="number" min="1" class="ov-min" value="1" /></td>' +
        '<td><input type="number" min="1" class="ov-max" value="100" /></td>' +
        '<td><input type="number" step="0.01" min="0" max="2" class="ov-mul" value="1.00" /></td>' +
        '<td><button type="button" class="button sc-remove-override-break">Remove</button></td>' +
        "</tr>"
    );
  });

  $(document).on("click", ".sc-remove-override-break", function () {
    $(this).closest("tr").remove();
  });

  $("#sc-save-override-breaks").on("click", function () {
    var key = $("#sc-override-sticker-select").val();
    if (!key) return;

    var breaks = [];
    $("#sc-override-breaks-tbody tr").each(function () {
      breaks.push({
        min: $(this).find(".ov-min").val(),
        max: $(this).find(".ov-max").val(),
        multiplier: $(this).find(".ov-mul").val(),
      });
    });

    $.post(scAdmin.ajax_url, {
      action: "sc_save_qty_overrides",
      nonce: scAdmin.nonce,
      sticker_key: key,
      breaks: breaks,
    }).done(function (res) {
      if (res.success) {
        overrides[key] = breaks;
        alert("Overrides saved for this sticker!");
        location.reload();
      } else {
        alert(res.data || "Error");
      }
    });
  });

  $("#sc-remove-overrides").on("click", function () {
    var key = $("#sc-override-sticker-select").val();
    if (!key) return;
    if (!confirm("Remove overrides for this sticker? Default breaks will be used instead.")) return;

    $.post(scAdmin.ajax_url, {
      action: "sc_remove_qty_overrides",
      nonce: scAdmin.nonce,
      sticker_key: key,
    }).done(function (res) {
      if (res.success) {
        delete overrides[key];
        renderOverrideBreaks([]);
        alert("Overrides removed.");
        location.reload();
      } else {
        alert(res.data || "Error");
      }
    });
  });
})(jQuery);
