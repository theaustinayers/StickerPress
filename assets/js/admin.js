(function ($) {
  "use strict";

  /* ── Generic row move up / down ── */
  $(document).on("click", ".sc-move-up", function () {
    var $row = $(this).closest("tr");
    var $prev = $row.prev("tr");
    if ($prev.length) $row.insertBefore($prev);
  });
  $(document).on("click", ".sc-move-down", function () {
    var $row = $(this).closest("tr");
    var $next = $row.next("tr");
    if ($next.length) $row.insertAfter($next);
  });

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
      var slug = m.slug || m;
      var label = m.label || (slug.charAt(0).toUpperCase() + slug.slice(1));
      html +=
        '<option value="' +
        slug +
        '"' +
        (selected === slug ? " selected" : "") +
        ">" +
        $("<span>").text(label).html() +
        "</option>";
    });
    return html;
  }

  function finishOptions(selected) {
    var html = "";
    $.each(scAdmin.finishes, function (_, f) {
      var slug = f.slug || f;
      var label = f.label || (slug.charAt(0).toUpperCase() + slug.slice(1));
      html +=
        '<option value="' +
        slug +
        '"' +
        (selected === slug ? " selected" : "") +
        ">" +
        $("<span>").text(label).html() +
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
      default_quantity: $("#sc-default-quantity").val(),
      accent_mode: $('input[name="sc_accent_mode"]:checked').val(),
      accent_color: $("#sc-accent-color").val(),
      accent_color2: $("#sc-accent-color2").val(),
      accent_angle: $("#sc-accent-angle").val(),
      hover_mode: $('input[name="sc_hover_mode"]:checked').val(),
      hover_color: $("#sc-hover-color").val(),
      hover_color2: $("#sc-hover-color2").val(),
      hover_angle: $("#sc-hover-angle").val(),
      safe_area_percent: $("#sc-safe-area-percent").val(),
      disclaimer_text: $("#sc-disclaimer-text").val(),
    }).done(function (res) {
      alert(res.success ? "Global settings saved!" : res.data || "Error");
    });
  });

  // Update hex display when color picker changes
  $("#sc-accent-color").on("input", function () {
    $("#sc-accent-color-hex").text($(this).val());
    updateAccentPreview();
  });
  $("#sc-accent-color2").on("input", function () {
    $("#sc-accent-color2-hex").text($(this).val());
    updateAccentPreview();
  });
  $("#sc-accent-angle").on("input", function () { updateAccentPreview(); });
  $("#sc-hover-color").on("input", function () {
    $("#sc-hover-color-hex").text($(this).val());
    updateHoverPreview();
  });
  $("#sc-hover-color2").on("input", function () {
    $("#sc-hover-color2-hex").text($(this).val());
    updateHoverPreview();
  });
  $("#sc-hover-angle").on("input", function () { updateHoverPreview(); });

  // Accent mode toggle
  $('input[name="sc_accent_mode"]').on("change", function () {
    if ($(this).val() === "gradient") {
      $(".sc-gradient-fields").show();
    } else {
      $(".sc-gradient-fields").hide();
    }
    updateAccentPreview();
  });

  // Hover mode toggle
  $('input[name="sc_hover_mode"]').on("change", function () {
    if ($(this).val() === "gradient") {
      $(".sc-hover-gradient-fields").show();
    } else {
      $(".sc-hover-gradient-fields").hide();
    }
    updateHoverPreview();
  });

  function updateAccentPreview() {
    var mode = $('input[name="sc_accent_mode"]:checked').val();
    var c1 = $("#sc-accent-color").val();
    var c2 = $("#sc-accent-color2").val();
    var angle = $("#sc-accent-angle").val() || 135;
    var bg = mode === "gradient" ? "linear-gradient(" + angle + "deg, " + c1 + ", " + c2 + ")" : c1;
    $("#sc-accent-preview").css("background", bg);
  }

  function updateHoverPreview() {
    var mode = $('input[name="sc_hover_mode"]:checked').val();
    var c1 = $("#sc-hover-color").val();
    var c2 = $("#sc-hover-color2").val();
    var angle = $("#sc-hover-angle").val() || 135;
    var bg = mode === "gradient" ? "linear-gradient(" + angle + "deg, " + c1 + ", " + c2 + ")" : c1;
    $("#sc-hover-preview").css("background", bg);
  }

  updateAccentPreview();
  updateHoverPreview();

  /* ── Save Sizes ── */
  $("#sc-sizes-form").on("submit", function (e) {
    e.preventDefault();
    var sizes = [];
    $("#sc-sizes-tbody tr").each(function () {
      sizes.push({
        label: $(this).find('input[name*="[label]"]').val(),
        width: $(this).find('input[name*="[width]"]').val(),
        height: $(this).find('input[name*="[height]"]').val(),
        min_qty: $(this).find('input[name*="[min_qty]"]').val() || 0,
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
      '<td><input type="number" min="0" name="sizes[' +
      idx +
      '][min_qty]" value="" placeholder="Global" style="width:70px;" /></td>' +
      '<td><button type="button" class="button sc-move-up" title="Move up">▲</button> <button type="button" class="button sc-move-down" title="Move down">▼</button> <button type="button" class="button sc-remove-size">Remove</button></td>' +
      "</tr>";
    $("#sc-sizes-tbody").append(row);
  });

  /* ── Remove Size ── */
  $(document).on("click", ".sc-remove-size", function () {
    $(this).closest("tr").remove();
  });

  /* ════════════════════════════════════════════
     MATERIALS – add / remove / save
     ════════════════════════════════════════════ */

  $("#sc-materials-form").on("submit", function (e) {
    e.preventDefault();
    var materials = [];
    $("#sc-materials-tbody tr").each(function () {
      materials.push({
        slug: $(this).find('input[name*="[slug]"]').val(),
        label: $(this).find('input[name*="[label]"]').val(),
      });
    });
    $.post(scAdmin.ajax_url, {
      action: "sc_save_materials",
      nonce: scAdmin.nonce,
      materials: materials,
    }).done(function (res) {
      alert(res.success ? "Materials saved!" : res.data || "Error");
      if (res.success) location.reload();
    });
  });

  $("#sc-add-material").on("click", function () {
    var idx = $("#sc-materials-tbody tr").length;
    $("#sc-materials-tbody").append(
      '<tr data-index="' + idx + '">' +
      '<td><input type="text" name="materials[' + idx + '][slug]" value="" placeholder="e.g. holographic" /></td>' +
      '<td><input type="text" name="materials[' + idx + '][label]" value="" placeholder="e.g. Holographic" /></td>' +
      '<td><button type="button" class="button sc-move-up" title="Move up">▲</button> <button type="button" class="button sc-move-down" title="Move down">▼</button> <button type="button" class="button sc-remove-material">Remove</button></td>' +
      '</tr>'
    );
  });

  $(document).on("click", ".sc-remove-material", function () {
    $(this).closest("tr").remove();
  });

  /* ════════════════════════════════════════════
     FINISHES – add / remove / save
     ════════════════════════════════════════════ */

  $("#sc-finishes-form").on("submit", function (e) {
    e.preventDefault();
    var finishes = [];
    $("#sc-finishes-tbody tr").each(function () {
      finishes.push({
        slug: $(this).find('input[name*="[slug]"]').val(),
        label: $(this).find('input[name*="[label]"]').val(),
      });
    });
    $.post(scAdmin.ajax_url, {
      action: "sc_save_finishes",
      nonce: scAdmin.nonce,
      finishes: finishes,
    }).done(function (res) {
      alert(res.success ? "Finishes saved!" : res.data || "Error");
      if (res.success) location.reload();
    });
  });

  $("#sc-add-finish").on("click", function () {
    var idx = $("#sc-finishes-tbody tr").length;
    $("#sc-finishes-tbody").append(
      '<tr data-index="' + idx + '">' +
      '<td><input type="text" name="finishes[' + idx + '][slug]" value="" placeholder="e.g. satin" /></td>' +
      '<td><input type="text" name="finishes[' + idx + '][label]" value="" placeholder="e.g. Satin" /></td>' +
      '<td><button type="button" class="button sc-move-up" title="Move up">▲</button> <button type="button" class="button sc-move-down" title="Move down">▼</button> <button type="button" class="button sc-remove-finish">Remove</button></td>' +
      '</tr>'
    );
  });

  $(document).on("click", ".sc-remove-finish", function () {
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
      '<td><button type="button" class="button sc-move-up" title="Move up">▲</button> <button type="button" class="button sc-move-down" title="Move down">▼</button> <button type="button" class="button sc-duplicate-pricing" title="Duplicate">⧉</button> <button type="button" class="button sc-remove-pricing">Remove</button></td>' +
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

  $(document).on("click", ".sc-duplicate-pricing", function () {
    var $row = $(this).closest("tr");
    var selects = $row.find("select");
    var idx = $("#sc-pricing-tbody tr").length;
    var newRow = pricingRow(
      idx,
      selects.eq(0).val(),
      selects.eq(1).val(),
      selects.eq(2).val(),
      selects.eq(3).val(),
      $row.find('input[type="number"]').val(),
      $row.find(".sc-pricing-visible").is(":checked")
    );
    $row.after(newRow);
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

  /* ════════════════════════════════════════════
     PROFIT CALCULATOR – real-time updates
     ════════════════════════════════════════════ */
  function calcProfit() {
    var rollW = parseFloat($("#sc-calc-roll-w").val()) || 0;
    var rollL = parseFloat($("#sc-calc-roll-l").val()) || 0;
    var rollCost = parseFloat($("#sc-calc-roll-cost").val()) || 0;
    var gap = parseFloat($("#sc-calc-gap").val()) || 0;
    var margin = parseFloat($("#sc-calc-margin").val()) || 0;
    var marginType = $('input[name="sc_calc_margin_type"]:checked').val();

    var $opt = $("#sc-calc-size option:selected");
    var sW = parseFloat($opt.attr("data-w")) || 1;
    var sH = parseFloat($opt.attr("data-h")) || 1;

    // Calculate stickers that fit
    var perRow = Math.floor(rollW / (sW + gap));
    var rows = Math.floor(rollL / (sH + gap));
    var total = perRow * rows;

    if (total <= 0) {
      $("#sc-calc-per-row, #sc-calc-rows, #sc-calc-total, #sc-calc-cost-each, #sc-calc-price-each, #sc-calc-profit-each, #sc-calc-total-cost, #sc-calc-revenue, #sc-calc-total-profit, #sc-calc-margin-pct, #sc-calc-recommended, #sc-calc-low-price, #sc-calc-low-profit, #sc-calc-med-price, #sc-calc-med-profit, #sc-calc-high-price, #sc-calc-high-profit").text("—").css("color", "");
      return;
    }

    var costEach = rollCost / total;

    // Sell price based on user margin
    var sellPrice;
    if (marginType === "dollar") {
      sellPrice = costEach + margin;
    } else {
      if (margin >= 100) margin = 99;
      sellPrice = costEach / (1 - margin / 100);
    }

    var profitEach = sellPrice - costEach;
    var totalCost = rollCost;
    var revenue = sellPrice * total;
    var totalProfit = revenue - totalCost;
    var marginPct = revenue > 0 ? ((totalProfit / revenue) * 100) : 0;

    // Recommended unit price at 35% margin
    var recommended = costEach / (1 - 0.35);

    // Profit tiers: Low 20%, Medium 45%, High 75%
    var lowPrice = costEach / (1 - 0.20);
    var medPrice = costEach / (1 - 0.45);
    var highPrice = costEach / (1 - 0.75);
    var lowProfit = (lowPrice - costEach) * total;
    var medProfit = (medPrice - costEach) * total;
    var highProfit = (highPrice - costEach) * total;

    // Color: green if profit >= 0, red if loss
    var profitColor = totalProfit >= 0 ? "#2e7d32" : "#c62828";
    var lossOrGain = totalProfit >= 0;

    $("#sc-calc-per-row").text(perRow);
    $("#sc-calc-rows").text(rows.toLocaleString());
    $("#sc-calc-total").text(total.toLocaleString());
    $("#sc-calc-cost-each").text("$" + costEach.toFixed(4));
    $("#sc-calc-price-each").text("$" + sellPrice.toFixed(4)).css("color", profitColor);
    $("#sc-calc-profit-each").text((lossOrGain ? "+$" : "-$") + Math.abs(profitEach).toFixed(4)).css("color", profitColor);
    $("#sc-calc-total-cost").text("$" + totalCost.toFixed(2));
    $("#sc-calc-revenue").text("$" + revenue.toFixed(2)).css("color", profitColor);
    $("#sc-calc-total-profit").text((lossOrGain ? "+$" : "-$") + Math.abs(totalProfit).toFixed(2)).css("color", profitColor);
    $("#sc-calc-margin-pct").text(marginPct.toFixed(1) + "%").css("color", profitColor);
    $("#sc-calc-recommended").text("$" + recommended.toFixed(4) + " per unit");

    // Profit tier outputs
    $("#sc-calc-low-price").text("$" + lowPrice.toFixed(4)).css("color", "#2e7d32");
    $("#sc-calc-low-profit").text("$" + lowProfit.toFixed(2)).css("color", "#2e7d32");
    $("#sc-calc-med-price").text("$" + medPrice.toFixed(4)).css("color", "#e65100");
    $("#sc-calc-med-profit").text("$" + medProfit.toFixed(2)).css("color", "#e65100");
    $("#sc-calc-high-price").text("$" + highPrice.toFixed(4)).css("color", "#1565c0");
    $("#sc-calc-high-profit").text("$" + highProfit.toFixed(2)).css("color", "#1565c0");

    // Highlight the results panel
    $("#sc-calc-results").css("border-color", profitColor);
  }

  $("#sc-calc-roll-w, #sc-calc-roll-l, #sc-calc-roll-cost, #sc-calc-size, #sc-calc-gap, #sc-calc-margin").on("input change", calcProfit);
  $('input[name="sc_calc_margin_type"]').on("change", calcProfit);
  calcProfit();

  /* ════════════════════════════════════════════
     CUT TYPE FEES – save
     ════════════════════════════════════════════ */
  $("#sc-cut-fees-form").on("submit", function (e) {
    e.preventDefault();
    var fees = {};
    $("#sc-cut-fees-tbody tr").each(function () {
      var cut = $(this).data("cut");
      fees[cut] = {
        amount: $(this).find(".sc-cut-fee-amount").val() || "0",
        type: $(this).find(".sc-cut-fee-type").val() || "flat",
      };
    });
    $.post(scAdmin.ajax_url, {
      action: "sc_save_cut_fees",
      nonce: scAdmin.nonce,
      cut_fees: fees,
    }).done(function (res) {
      alert(res.success ? "Cut type fees saved!" : res.data || "Error");
    });
  });
})(jQuery);
