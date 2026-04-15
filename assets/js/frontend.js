(function ($) {
  "use strict";

  let uploadedFile = null;
  let uploadedFilename = "";
  let originalFilename = ""; // original file before bg removal
  let fileIsVector = false; // PDF, AI, PSD, SVG — no pixel preview
  let editScale = 100;
  let editRotate = 0;
  let editX = 0;
  let editY = 0;

  /* ── Helpers ── */
  function getEffectiveMinQty() {
    var sIdx = parseInt($("#sc-size").val(), 10) || 0;
    var sizeObj = scData.sizes[sIdx] || {};
    var sizeMin = parseInt(sizeObj.min_qty, 10) || 0;
    var globalMin = parseInt(scData.min_quantity, 10) || 1;
    return sizeMin > 0 ? sizeMin : globalMin;
  }

  function getSelections() {
    var lam = $('input[name="sc_laminated"]:checked').val() || "no";
    var minQty = getEffectiveMinQty();
    return {
      cut: $('input[name="sc_cut"]:checked').val() || "square",
      sizeIndex: parseInt($("#sc-size").val(), 10) || 0,
      material: $("#sc-material").val() || "vinyl",
      finish: $('input[name="sc_finish"]:checked').val() || "glossy",
      laminated: lam,
      whiteBorder: (parseInt($("#sc-border-level").val(), 10) || 0) > 0 ? "yes" : "no",
      borderLevel: parseInt($("#sc-border-level").val(), 10) || 0,
      quantity: Math.max(minQty, parseInt($("#sc-quantity").val(), 10) || minQty),
    };
  }

  function calcPrice(sel) {
    const key =
      sel.sizeIndex +
      "_" +
      sel.material +
      "_" +
      sel.finish +
      "_" +
      sel.laminated;
    let basePrice = parseFloat(scData.pricing[key]) || 0;

    var cutFees = scData.cut_fees || {};
    var cutFee = cutFees[sel.cut];
    if (cutFee) {
      var feeAmt = parseFloat(cutFee.amount) || 0;
      if (feeAmt > 0) {
        if (cutFee.type === 'percent') {
          basePrice *= (1 + feeAmt / 100);
        } else {
          basePrice += feeAmt;
        }
      }
    }

    let breaks = [];
    if (
      scData.qty_overrides &&
      typeof scData.qty_overrides === "object" &&
      scData.qty_overrides[key] &&
      scData.qty_overrides[key].length
    ) {
      breaks = scData.qty_overrides[key];
    } else if (scData.qty_breaks && scData.qty_breaks.length) {
      breaks = scData.qty_breaks;
    }

    let multiplier = 1;
    if (breaks.length) {
      for (const brk of breaks) {
        if (sel.quantity >= brk.min && sel.quantity <= brk.max) {
          multiplier = parseFloat(brk.multiplier);
          break;
        }
      }
      const last = breaks[breaks.length - 1];
      if (sel.quantity > last.max) {
        multiplier = parseFloat(last.multiplier);
      }
    }

    const priceEach = basePrice * multiplier;
    const total = priceEach * sel.quantity;
    const discount = Math.round((1 - multiplier) * 100);

    return { priceEach, total, discount, multiplier };
  }

  /* ── Preview Update ── */
  function updatePreview() {
    const sel = getSelections();
    const $art = $("#sc-preview-artwork");
    const $shine = $("#sc-preview-shine");
    const $laminate = $("#sc-preview-laminate");
    const $img = $("#sc-preview-img");
    const $placeholder = $("#sc-preview-placeholder");

    $art
      .removeClass(
        "sc-shape-square sc-shape-round sc-shape-rounded-rect sc-shape-die-cut"
      )
      .addClass("sc-shape-" + sel.cut);

    if (sel.finish === "glossy") {
      $shine.addClass("sc-shine-active");
      $art.removeClass("sc-matte-surface").addClass("sc-glossy-surface");
    } else {
      $shine.removeClass("sc-shine-active");
      $art.removeClass("sc-glossy-surface").addClass("sc-matte-surface");
    }

    if (sel.laminated === "yes") {
      $laminate.addClass("sc-lam-active");
    } else {
      $laminate.removeClass("sc-lam-active");
    }

    $art
      .removeClass("sc-mat-vinyl sc-mat-paper sc-mat-clear")
      .addClass("sc-mat-" + sel.material);

    if (uploadedFile) {
      if (fileIsVector) {
        $img.hide();
        $placeholder.text("Vector file uploaded — preview not available").show();
        $art.hide();
        $("#sc-diecut-canvas").hide();
        $("#sc-editor-toolbar").hide();
      } else {
        $img.show();
        $placeholder.hide();
        $art.show();
        $("#sc-editor-toolbar").show();

        // Apply editor transforms to the whole artwork wrapper
        var transformStr = "translate(" + editX + "px, " + editY + "px) scale(" + (editScale / 100) + ") rotate(" + editRotate + "deg)";
        $art.css("transform", transformStr);
        $img.css("transform", "");

        // Toggle white border visibility and scale outline offset
        var borderLevel = sel.borderLevel;
        if (borderLevel > 0) {
          $art.addClass("sc-border-on").removeClass("sc-border-off");
          // Scale the CSS outline offset: level 1=4px, 2=8px, 3=12px, 4=16px, 5=20px
          var outlineOff = borderLevel * 4;
          $("#sc-cut-outline").css({
            top: -outlineOff + "px",
            left: -outlineOff + "px",
            right: -outlineOff + "px",
            bottom: -outlineOff + "px"
          });
        } else {
          $art.addClass("sc-border-off").removeClass("sc-border-on");
        }

        if (sel.cut === "die-cut") {
          drawDieCutOutline();
        } else {
          $("#sc-diecut-canvas").hide();
        }

        // Update safe area size based on selected sticker dimensions
        var sizeObj = scData.sizes[sel.sizeIndex] || {};
        var saW = parseFloat(sizeObj.width) || 1;
        var saH = parseFloat(sizeObj.height) || 1;
        var saPct = (parseInt(scData.safe_area_percent, 10) || 100) / 100;
        saW *= saPct;
        saH *= saPct;
        var saMax = 304; // max dimension in px (60% larger)
        var saScale = Math.min(saMax / saW, saMax / saH);
        $("#sc-safe-area").css({ width: Math.round(saW * saScale) + "px", height: Math.round(saH * saScale) + "px" }).show();
      }
    } else {
      $img.hide();
      $placeholder.text("Upload artwork to see preview").show();
      $art.hide();
      $("#sc-diecut-canvas").hide();
      $("#sc-editor-toolbar").hide();
      $("#sc-safe-area").hide();
    }
  }

  /* ─────────────────────────────────────────────
     Die-Cut Outline: true contour tracing
     Casts rays from centroid to find the actual silhouette edge,
     preserving concavities (bottle neck, irregular shapes, etc).
     Draws white fill (sticker backing) then dashed cut line.
     ───────────────────────────────────────────── */
  function drawDieCutOutline() {
    const sel = getSelections();
    const showBorder = sel.whiteBorder === "yes";
    const borderLevel = sel.borderLevel;
    const $canvas = $("#sc-diecut-canvas");
    const $container = $("#sc-preview-container");
    const $art = $("#sc-preview-artwork");
    const img = document.getElementById("sc-preview-img");

    if (!img || !img.naturalWidth) {
      $canvas.hide();
      return;
    }

    const canvas = $canvas[0];
    const artW = $art.outerWidth();
    const artH = $art.outerHeight();

    canvas.width = $container.innerWidth();
    canvas.height = $container.innerHeight();
    $canvas.css({
      display: "block",
      width: canvas.width + "px",
      height: canvas.height + "px",
    });

    const ctx = canvas.getContext("2d");
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    // Draw image to temp canvas to read pixel alpha
    const tmp = document.createElement("canvas");
    const size = 400; // higher resolution for precision
    tmp.width = size;
    tmp.height = size;
    const tctx = tmp.getContext("2d");

    // Maintain aspect ratio — leave margin so boundary pixels aren't at canvas edge
    const margin = 6;
    const natW = img.naturalWidth;
    const natH = img.naturalHeight;
    const imgScale = Math.min((size - margin * 2) / natW, (size - margin * 2) / natH);
    const drawW = natW * imgScale;
    const drawH = natH * imgScale;
    const offX = (size - drawW) / 2;
    const offY = (size - drawH) / 2;
    tctx.clearRect(0, 0, size, size);
    tctx.drawImage(img, offX, offY, drawW, drawH);

    let imageData;
    try {
      imageData = tctx.getImageData(0, 0, size, size);
    } catch (e) {
      // CORS fallback
      const p = 10;
      ctx.setLineDash([6, 4]);
      ctx.strokeStyle = "#000";
      ctx.lineWidth = 1.5;
      ctx.strokeRect((canvas.width - artW) / 2 - p, (canvas.height - artH) / 2 - p, artW + p * 2, artH + p * 2);
      return;
    }

    const data = imageData.data;
    const alphaThreshold = 20;

    // Build binary alpha mask
    const alpha = new Uint8Array(size * size);
    for (let i = 0; i < size * size; i++) {
      alpha[i] = data[i * 4 + 3] > alphaThreshold ? 1 : 0;
    }

    // Small dilation (2px) just to close 1-2px anti-aliasing gaps
    const dilateR = 2;
    const clean = new Uint8Array(size * size);
    for (let y = 0; y < size; y++) {
      for (let x = 0; x < size; x++) {
        if (alpha[y * size + x]) { clean[y * size + x] = 1; continue; }
        let found = false;
        for (let dy = -dilateR; dy <= dilateR && !found; dy++) {
          for (let dx = -dilateR; dx <= dilateR && !found; dx++) {
            if (dx * dx + dy * dy > dilateR * dilateR) continue;
            const nx = x + dx, ny = y + dy;
            if (nx >= 0 && ny >= 0 && nx < size && ny < size && alpha[ny * size + nx]) {
              found = true;
            }
          }
        }
        clean[y * size + x] = found ? 1 : 0;
      }
    }

    // Find bounding box of opaque pixels (use bounding-box center, not mass centroid)
    let minX = size, maxX = 0, minY = size, maxY = 0, count = 0;
    for (let y = 0; y < size; y++) {
      for (let x = 0; x < size; x++) {
        if (clean[y * size + x]) {
          if (x < minX) minX = x;
          if (x > maxX) maxX = x;
          if (y < minY) minY = y;
          if (y > maxY) maxY = y;
          count++;
        }
      }
    }
    if (count < 10) { $canvas.hide(); return; }
    const cx = (minX + maxX) / 2;
    const cy = (minY + maxY) / 2;

    // Full boundary detection: every opaque pixel adjacent to a transparent pixel
    // This guarantees 100% contour coverage with no angular blind spots
    const boundaryPts = [];
    for (let y = 1; y < size - 1; y++) {
      for (let x = 1; x < size - 1; x++) {
        if (!clean[y * size + x]) continue;
        // Check 4-connected neighbors — if any is transparent, this is a boundary pixel
        if (!clean[(y - 1) * size + x] || !clean[(y + 1) * size + x] ||
            !clean[y * size + (x - 1)] || !clean[y * size + (x + 1)]) {
          boundaryPts.push([x, y]);
        }
      }
    }

    // Bin by angle from center, keep outermost point per bin
    const numBins = 1440;
    const binned = new Array(numBins);
    for (let i = 0; i < numBins; i++) binned[i] = null;
    for (let i = 0; i < boundaryPts.length; i++) {
      const pt = boundaryPts[i];
      let angle = Math.atan2(pt[1] - cy, pt[0] - cx);
      if (angle < 0) angle += 2 * Math.PI;
      const bin = Math.floor(angle / (2 * Math.PI) * numBins) % numBins;
      const dist = Math.sqrt((pt[0] - cx) * (pt[0] - cx) + (pt[1] - cy) * (pt[1] - cy));
      if (!binned[bin] || dist > binned[bin][2]) {
        binned[bin] = [pt[0], pt[1], dist];
      }
    }
    const contourPoints = [];
    for (let i = 0; i < numBins; i++) {
      if (binned[i]) contourPoints.push([binned[i][0], binned[i][1]]);
    }
    if (contourPoints.length < 10) { $canvas.hide(); return; }

    // Light smoothing (window=3) — just enough to remove jaggies, stays tight
    function smoothPass(pts, w) {
      const out = [];
      for (let i = 0; i < pts.length; i++) {
        let sx = 0, sy = 0, wt = 0;
        for (let j = -w; j <= w; j++) {
          const idx = (i + j + pts.length) % pts.length;
          const weight = 1 - Math.abs(j) / (w + 1);
          sx += pts[idx][0] * weight;
          sy += pts[idx][1] * weight;
          wt += weight;
        }
        out.push([sx / wt, sy / wt]);
      }
      return out;
    }
    let smoothed = smoothPass(contourPoints, 3);
    smoothed = smoothPass(smoothed, 2);

    // White border expansion amount scales with border level
    // Level 0=0, 1=3, 2=6, 3=10, 4=14, 5=20  (in trace canvas pixels)
    var borderPadMap = [0, 3, 6, 10, 14, 20];
    const borderPad = showBorder ? (borderPadMap[borderLevel] || 0) : 0;
    // Dashed line sits just outside the border
    const linePad = borderPad + 2;

    // Expand outward using surface normals (not radial from center)
    // This gives uniform offset regardless of center position
    function expandPath(pts, amount) {
      if (amount === 0) return pts.slice();
      const out = [];
      const n = pts.length;
      for (let i = 0; i < n; i++) {
        const prev = pts[(i - 1 + n) % n];
        const next = pts[(i + 1) % n];
        // Tangent direction
        const tx = next[0] - prev[0];
        const ty = next[1] - prev[1];
        const tlen = Math.sqrt(tx * tx + ty * ty) || 1;
        // Outward normal (perpendicular, pointing away from center)
        let nx = -ty / tlen;
        let ny = tx / tlen;
        // Ensure normal points outward (away from center)
        const dx = pts[i][0] - cx;
        const dy = pts[i][1] - cy;
        if (nx * dx + ny * dy < 0) { nx = -nx; ny = -ny; }
        out.push([pts[i][0] + nx * amount, pts[i][1] + ny * amount]);
      }
      return out;
    }

    const borderPath = expandPath(smoothed, borderPad);
    // Single outline path: sits just outside the border
    const outlinePath = expandPath(smoothed, linePad);

    // Resample for consistent dash spacing
    function resample(pts, numOut) {
      let perimeter = 0;
      for (let i = 0; i < pts.length; i++) {
        const nx = (i + 1) % pts.length;
        const dx = pts[nx][0] - pts[i][0];
        const dy = pts[nx][1] - pts[i][1];
        perimeter += Math.sqrt(dx * dx + dy * dy);
      }
      const step = perimeter / numOut;
      const result = [pts[0]];
      let carry = 0;
      for (let i = 0; i < pts.length && result.length < numOut; i++) {
        const nx = (i + 1) % pts.length;
        const segDx = pts[nx][0] - pts[i][0];
        const segDy = pts[nx][1] - pts[i][1];
        const segLen = Math.sqrt(segDx * segDx + segDy * segDy);
        if (segLen === 0) continue;
        let pos = carry;
        while (pos + step <= segLen + 0.001 && result.length < numOut) {
          pos += step;
          const t = pos / segLen;
          result.push([pts[i][0] + segDx * t, pts[i][1] + segDy * t]);
        }
        carry = pos - segLen;
      }
      return result;
    }

    const resampledBorder = resample(borderPath, 500);
    const resampledOutline = resample(outlinePath, 500);

    // Map to container coordinates, accounting for editor transforms
    // Convert from trace-canvas coords to art-element coords:
    // Image occupies (offX..offX+drawW, offY..offY+drawH) in trace canvas
    // and maps to (0..artW, 0..artH) in the art element
    const edScale = editScale / 100;
    const edRad = editRotate * Math.PI / 180;
    // Art center: canvas covers full padding box, art is flex-centered
    const artCx = canvas.width / 2 + editX;
    const artCy = canvas.height / 2 + editY;

    function toScreen(pts) {
      return pts.map(function(p) {
        // Map trace-canvas point to art-element local coords
        var artLocalX = (p[0] - offX) / drawW * artW;
        var artLocalY = (p[1] - offY) / drawH * artH;
        // Point relative to art center
        var lx = (artLocalX - artW / 2) * edScale;
        var ly = (artLocalY - artH / 2) * edScale;
        // Apply rotation
        var rx = lx * Math.cos(edRad) - ly * Math.sin(edRad);
        var ry = lx * Math.sin(edRad) + ly * Math.cos(edRad);
        return [artCx + rx, artCy + ry];
      });
    }

    const screenBorder = toScreen(resampledBorder);
    const screenOutline = toScreen(resampledOutline);

    function drawSmoothPath(points) {
      ctx.beginPath();
      ctx.moveTo(points[0][0], points[0][1]);
      for (let i = 0; i < points.length; i++) {
        const cur = points[i];
        const nxt = points[(i + 1) % points.length];
        const mx = (cur[0] + nxt[0]) / 2;
        const my = (cur[1] + nxt[1]) / 2;
        ctx.quadraticCurveTo(cur[0], cur[1], mx, my);
      }
      ctx.closePath();
    }

    // 1) White sticker backing fill (only if border enabled)
    if (showBorder) {
      ctx.save();
      drawSmoothPath(screenBorder);
      ctx.fillStyle = "#fff";
      ctx.fill();
      ctx.restore();
    }

    // 2) Single dashed cut line
    ctx.save();
    drawSmoothPath(screenOutline);
    ctx.setLineDash([7, 4]);
    ctx.strokeStyle = "#000";
    ctx.lineWidth = 2;
    ctx.stroke();
    ctx.restore();

  }

  /* ── Pricing Update ── */
  function updatePricing() {
    const sel = getSelections();
    const price = calcPrice(sel);
    const sizeObj = scData.sizes[sel.sizeIndex] || {};

    $("#sc-price-size").text(sizeObj.label || "-");
    $("#sc-price-material").text(
      sel.material.charAt(0).toUpperCase() + sel.material.slice(1)
    );
    $("#sc-price-finish").text(
      sel.finish.charAt(0).toUpperCase() + sel.finish.slice(1)
    );
    $("#sc-price-lam").text(sel.laminated === "yes" ? "Laminated" : "Non-Laminated");
    var cutLabel = sel.cut.replace(/-/g, " ").replace(/\b\w/g, function(c) { return c.toUpperCase(); });
    var cfData = scData.cut_fees || {};
    var cfEntry = cfData[sel.cut];
    if (cfEntry && parseFloat(cfEntry.amount) > 0) {
      cutLabel += cfEntry.type === 'percent' ? ' (+' + cfEntry.amount + '%)' : ' (+$' + parseFloat(cfEntry.amount).toFixed(2) + ')';
    }
    $("#sc-price-cut").text(cutLabel);
    $("#sc-price-each").text("$" + price.priceEach.toFixed(2));
    $("#sc-price-qty").text(sel.quantity.toLocaleString());

    if (price.discount > 0) {
      $("#sc-discount-row").show();
      $("#sc-price-discount").text("-" + price.discount + "%");
    } else {
      $("#sc-discount-row").hide();
    }

    $("#sc-price-total").text("$" + price.total.toFixed(2));
    $("#sc-add-to-cart").prop("disabled", !uploadedFile);

    // Hide pricing breakdown rows for globally single-value option groups
    var gS = unique(allConfigs, 'sizeIndex');
    var gM = unique(allConfigs, 'material');
    var gF = unique(allConfigs, 'finish');
    var gL = unique(allConfigs, 'laminated');
    $("#sc-price-size").closest('.sc-price-row').toggle(gS.length > 1);
    $("#sc-price-material").closest('.sc-price-row').toggle(gM.length > 1);
    $("#sc-price-finish").closest('.sc-price-row').toggle(gF.length > 1);
    $("#sc-price-lam").closest('.sc-price-row').toggle(gL.length > 1);
  }

  /* ── Available Options Filter (top-down cascade: Size → Material → Finish → Lamination) ── */
  var allConfigs = [];
  var availableOptions = { sizeIndex: [], material: [], finish: [], laminated: [] };

  // Build material order and labels from scData (dynamic from admin)
  var materialOrder = [];
  var materialLabels = {};
  if (scData.materials && scData.materials.length) {
    for (var mi = 0; mi < scData.materials.length; mi++) {
      var mat = scData.materials[mi];
      materialOrder.push(mat.slug);
      materialLabels[mat.slug] = mat.label;
    }
  }
  // Build finish labels from scData
  var finishLabels = {};
  if (scData.finishes && scData.finishes.length) {
    for (var fi = 0; fi < scData.finishes.length; fi++) {
      finishLabels[scData.finishes[fi].slug] = scData.finishes[fi].label;
    }
  }

  function parseConfigs() {
    allConfigs = [];
    for (var key in scData.pricing) {
      var parts = key.split('_');
      if (parts.length !== 4) continue;
      allConfigs.push({
        sizeIndex: parseInt(parts[0], 10),
        material: parts[1],
        finish: parts[2],
        laminated: parts[3]
      });
    }
  }

  function unique(arr, dim) {
    var vals = [];
    for (var i = 0; i < arr.length; i++) {
      if (vals.indexOf(arr[i][dim]) === -1) vals.push(arr[i][dim]);
    }
    return vals;
  }

  function setRadio(groupSel, dataAttr, value) {
    $(groupSel).removeClass('sc-selected');
    $(groupSel + "[data-" + dataAttr + "='" + value + "']")
      .addClass('sc-selected')
      .find('input').prop('checked', true);
  }

  function updateAvailableOptions() {
    if (!allConfigs.length) return;
    var pool, aS, aM, aF, aL;

    // ── 1) SIZES: always show all sizes that have any visible config ──
    aS = unique(allConfigs, 'sizeIndex');
    var curSize = parseInt($("#sc-size").val(), 10);
    var $sizeSelect = $("#sc-size");
    $sizeSelect.empty();
    for (var i = 0; i < scData.sizes.length; i++) {
      if (aS.indexOf(i) !== -1) {
        $sizeSelect.append($('<option>').val(i).text(scData.sizes[i].label));
      }
    }
    // Keep current size if still valid, otherwise pick first
    if (aS.indexOf(curSize) === -1) curSize = aS[0];
    $sizeSelect.val(curSize);

    // ── 2) MATERIALS: filter by selected size ──
    pool = allConfigs.filter(function(c) { return c.sizeIndex === curSize; });
    aM = unique(pool, 'material');
    var curMat = $("#sc-material").val();
    var $matSelect = $("#sc-material");
    $matSelect.empty();
    for (var j = 0; j < materialOrder.length; j++) {
      if (aM.indexOf(materialOrder[j]) !== -1) {
        $matSelect.append($('<option>').val(materialOrder[j]).text(materialLabels[materialOrder[j]]));
      }
    }
    if (aM.indexOf(curMat) === -1) curMat = aM[0];
    $matSelect.val(curMat);

    // ── 3) FINISH: filter by selected size + material ──
    pool = pool.filter(function(c) { return c.material === curMat; });
    aF = unique(pool, 'finish');
    $(".sc-finish-option").each(function() {
      $(this).toggle(aF.indexOf($(this).data('finish')) !== -1);
    });
    var curF = $('input[name="sc_finish"]:checked').val();
    if (aF.indexOf(curF) === -1 && aF.length) {
      setRadio('.sc-finish-option', 'finish', aF[0]);
      curF = aF[0];
    }

    // ── 4) LAMINATION: filter by selected size + material + finish ──
    pool = pool.filter(function(c) { return c.finish === curF; });
    aL = unique(pool, 'laminated');

    // If only "no" is available, show static label; if both, show toggle buttons
    var hasYes = aL.indexOf('yes') !== -1;
    var hasNo  = aL.indexOf('no') !== -1;
    $(".sc-lam-static").toggle(!hasYes && hasNo);
    $(".sc-lamination-options").toggle(hasYes);

    if (hasYes) {
      $(".sc-lamination-group .sc-lam-option").each(function() {
        $(this).toggle(aL.indexOf($(this).data('lam')) !== -1);
      });
    }

    var curL = $('input[name="sc_laminated"]:checked').val();
    if (aL.indexOf(curL) === -1 && aL.length) {
      // Prefer "no" (non-laminated) when current selection isn't available
      var defaultLam = aL.indexOf('no') !== -1 ? 'no' : aL[0];
      setRadio('.sc-lamination-group .sc-lam-option', 'lam', defaultLam);
    }

    // Store for pricing panel visibility
    availableOptions.sizeIndex = aS;
    availableOptions.material = aM;
    availableOptions.finish = aF;
    availableOptions.laminated = aL;

    // Hide entire option group only when ALL visible configs globally have the same value
    // (i.e. the dimension is meaningless across the entire product catalog, not just the current selection)
    var globalSizes     = unique(allConfigs, 'sizeIndex');
    var globalMaterials = unique(allConfigs, 'material');
    var globalFinishes  = unique(allConfigs, 'finish');
    var globalLam       = unique(allConfigs, 'laminated');

    $("#sc-size").closest('.sc-option-group').toggle(globalSizes.length > 1);
    $("#sc-material").closest('.sc-option-group').toggle(globalMaterials.length > 1);
    $(".sc-finish-option").closest('.sc-option-group').toggle(globalFinishes.length > 1);
    // Lamination group is always visible — it shows static "Non-Laminated" or toggle buttons
  }

  var lastSizeIndex = -1;
  var updatingAll = false;

  function updateAll() {
    if (updatingAll) return;
    updatingAll = true;
    updateAvailableOptions();
    // Enforce per-size minimum quantity and auto-set default on size change
    var minQty = getEffectiveMinQty();
    var $qty = $("#sc-quantity");
    var curSize = parseInt($("#sc-size").val(), 10) || 0;
    $qty.attr("min", minQty);
    if (curSize !== lastSizeIndex) {
      // Size changed — set quantity to this size's minimum
      $qty.val(minQty);
      lastSizeIndex = curSize;
    } else if (parseInt($qty.val(), 10) < minQty) {
      $qty.val(minQty);
    }
    updatePreview();
    updatePricing();
    updatingAll = false;
  }

  /* ── File Upload ── */
  function handleFile(file) {
    if (!file) return;

    var ext = file.name.toLowerCase().split(".").pop();
    var allowedExts = ["png","jpg","jpeg","svg","webp","pdf","ai","psd"];
    if (!allowedExts.includes(ext)) {
      alert("Please upload a PNG, JPG, SVG, WebP, PDF, AI, or PSD file.");
      return;
    }

    if (file.size > scData.max_upload) {
      alert("File too large.");
      return;
    }

    var fd = new FormData();
    fd.append("action", "sc_upload_artwork");
    fd.append("nonce", scData.nonce);
    fd.append("artwork", file);

    $("#sc-upload-zone").addClass("sc-uploading");

    $.ajax({
      url: scData.ajax_url,
      type: "POST",
      data: fd,
      processData: false,
      contentType: false,
      success: function (res) {
        $("#sc-upload-zone").removeClass("sc-uploading");
        if (res.success) {
          uploadedFile = res.data.url;
          uploadedFilename = res.data.filename;
          originalFilename = res.data.filename;
          fileIsVector = !!res.data.is_vector;

          if (fileIsVector) {
            $("#sc-uploaded-img").attr("src", "");
            $("#sc-remove-bg").hide();
          } else {
            $("#sc-uploaded-img").attr("src", res.data.url);
            $("#sc-preview-img").attr("src", res.data.url);
            $("#sc-remove-bg").show();
          }

          $("#sc-upload-preview-wrap").show();
          $("#sc-upload-zone").hide();
          updateAll();
        } else {
          alert(res.data || "Upload failed.");
        }
      },
      error: function () {
        $("#sc-upload-zone").removeClass("sc-uploading");
        alert("Upload failed. Please try again.");
      },
    });
  }

  /* ── Init ── */
  $(function () {
    // Parse visible pricing into config objects for cascading filter
    parseConfigs();

    // Set initial size tracking and enforce minimum quantity
    lastSizeIndex = parseInt($("#sc-size").val(), 10) || 0;
    var minQty = getEffectiveMinQty();
    $("#sc-quantity").attr("min", minQty);
    // Default quantity = this size's min qty
    $("#sc-quantity").val(minQty);

    var $zone = $("#sc-upload-zone");

    $zone.on("dragover dragenter", function (e) {
      e.preventDefault();
      e.stopPropagation();
      $(this).addClass("sc-drag-over");
    });

    $zone.on("dragleave drop", function (e) {
      e.preventDefault();
      e.stopPropagation();
      $(this).removeClass("sc-drag-over");
    });

    $zone.on("drop", function (e) {
      var files = e.originalEvent.dataTransfer.files;
      if (files.length) handleFile(files[0]);
    });

    $("#sc-file-input").on("change", function () {
      if (this.files.length) handleFile(this.files[0]);
    });

    // Remove upload
    $("#sc-remove-upload").on("click", function () {
      if (uploadedFilename) {
        $.post(scData.ajax_url, {
          action: "sc_remove_artwork",
          nonce: scData.nonce,
          filename: uploadedFilename,
        });
      }
      uploadedFile = null;
      uploadedFilename = "";
      originalFilename = "";
      fileIsVector = false;
      editScale = 100;
      editRotate = 0;
      editX = 0;
      editY = 0;
      $("#sc-edit-scale").val(100);
      $("#sc-edit-rotate").val(0);
      $("#sc-edit-scale-val").text("100%");
      $("#sc-edit-rotate-val").text("0°");
      $("#sc-uploaded-img").attr("src", "");
      $("#sc-preview-img").attr("src", "");
      $("#sc-upload-preview-wrap").hide();
      $("#sc-upload-zone").show();
      $("#sc-file-input").val("");
      $("#sc-remove-bg").show();
      updateAll();
    });

    // Remove background function (reusable)
    function doRemoveBackground() {
      if (!(originalFilename || uploadedFilename)) return;
      var $btn = $("#sc-remove-bg");
      var $status = $("#sc-bg-status");
      $btn.prop("disabled", true).text("Processing...");
      $("#sc-bg-tolerance").prop("disabled", true);
      $status.show().text("Removing background — this may take a moment...");

      $.post(scData.ajax_url, {
        action: "sc_remove_background",
        nonce: scData.nonce,
        filename: originalFilename || uploadedFilename,
        tolerance: parseInt($("#sc-bg-tolerance").val(), 10) || 120,
      })
        .done(function (res) {
          if (res.success) {
            uploadedFile = res.data.url;
            uploadedFilename = res.data.filename;
            var bust = "?v=" + Date.now();
            $("#sc-uploaded-img").attr("src", res.data.url + bust);
            $("#sc-preview-img").attr("src", res.data.url + bust);
            $status.text("Background removed!");
            setTimeout(function () { $status.fadeOut(); }, 3000);
            updateAll();
          } else {
            $status.text(res.data || "Could not remove background.");
          }
        })
        .fail(function () {
          $status.text("Error removing background. Please try again.");
        })
        .always(function () {
          $btn.prop("disabled", false).text("Remove Background");
          $("#sc-bg-tolerance").prop("disabled", false);
        });
    }

    // Manual remove background
    $("#sc-remove-bg").on("click", function () {
      doRemoveBackground();
    });

    $(".sc-cut-option").on("click", function () {
      $(".sc-cut-option").removeClass("sc-selected");
      $(this).addClass("sc-selected");
      $(this).find("input").prop("checked", true);
      updateAll();
    });

    $(".sc-finish-option").on("click", function () {
      $(".sc-finish-option").removeClass("sc-selected");
      $(this).addClass("sc-selected");
      $(this).find("input").prop("checked", true);
      updateAll();
    });

    $(".sc-lamination-options .sc-lam-option").on("click", function () {
      $(".sc-lamination-options .sc-lam-option").removeClass("sc-selected");
      $(this).addClass("sc-selected");
      $(this).find("input").prop("checked", true);
      updateAll();
    });

    $(".sc-border-options .sc-lam-option").on("click", function () {
      $(".sc-border-options .sc-lam-option").removeClass("sc-selected");
      $(this).addClass("sc-selected");
      $(this).find("input").prop("checked", true);
      updateAll();
    });

    $("#sc-border-level").on("input", function () {
      var lvl = parseInt($(this).val(), 10);
      $("#sc-border-level-val").text(lvl === 0 ? "None" : "Level " + lvl);
      updateAll();
    });

    $("#sc-size, #sc-material").on("change", updateAll);

    // Editor controls
    var bgToleranceTimer = null;
    $("#sc-bg-tolerance").on("input", function () {
      $("#sc-bg-tolerance-val").text($(this).val());
    });
    $("#sc-bg-tolerance").on("change", function () {
      // Auto re-apply background removal when slider is released
      if (originalFilename) {
        clearTimeout(bgToleranceTimer);
        bgToleranceTimer = setTimeout(function () {
          doRemoveBackground();
        }, 300);
      }
    });
    $("#sc-edit-scale").on("input", function () {
      editScale = parseInt($(this).val(), 10);
      $("#sc-edit-scale-val").text(editScale + "%");
      updatePreview();
    });
    $("#sc-edit-rotate").on("input", function () {
      editRotate = parseInt($(this).val(), 10);
      $("#sc-edit-rotate-val").text(editRotate + "°");
      updatePreview();
    });
    $("#sc-edit-reset").on("click", function () {
      editScale = 100;
      editRotate = 0;
      editX = 0;
      editY = 0;
      $("#sc-edit-scale").val(100);
      $("#sc-edit-rotate").val(0);
      $("#sc-edit-scale-val").text("100%");
      $("#sc-edit-rotate-val").text("0°");
      updatePreview();
    });

    // Drag-to-move image in preview
    (function () {
      var dragging = false, startX, startY, origX, origY;
      var $container = $("#sc-preview-container");

      function onStart(e) {
        if (!uploadedFile || fileIsVector) return;
        e.preventDefault();
        dragging = true;
        var ev = e.originalEvent.touches ? e.originalEvent.touches[0] : e;
        startX = ev.clientX;
        startY = ev.clientY;
        origX = editX;
        origY = editY;
        $container.addClass("sc-dragging");
      }
      function onMove(e) {
        if (!dragging) return;
        var ev = e.originalEvent.touches ? e.originalEvent.touches[0] : e;
        editX = origX + (ev.clientX - startX);
        editY = origY + (ev.clientY - startY);
        updatePreview();
      }
      function onEnd() {
        if (!dragging) return;
        dragging = false;
        $container.removeClass("sc-dragging");
      }

      $container.on("mousedown touchstart", onStart);
      $(document).on("mousemove touchmove", onMove);
      $(document).on("mouseup touchend", onEnd);
    })();

    $("#sc-quantity").on("input change", function () {
      var minQty = getEffectiveMinQty();
      var val = parseInt($(this).val(), 10);
      var $msg = $("#sc-min-qty-msg");
      if (val < minQty) {
        $(this).val(minQty);
        $msg.html("Minimum order quantity is " + minQty + ". Please contact us for lower quantity orders.").show();
      } else {
        $msg.hide();
      }
      updateAll();
    });

    // ── Generate proof canvas: composites artwork exactly as preview ──
    function generateProofCanvas(sel, imgEl, callback) {
      var natW = imgEl.naturalWidth;
      var natH = imgEl.naturalHeight;

      // Cap proof resolution to prevent oversized POST (base64) exceeding server limits
      var maxDim = 2000;
      if (natW > maxDim || natH > maxDim) {
        var scale = maxDim / Math.max(natW, natH);
        natW = Math.round(natW * scale);
        natH = Math.round(natH * scale);
      }

      var showBorder = sel.whiteBorder === "yes";
      var borderLevel = sel.borderLevel;
      var cut = sel.cut;

      // Border padding for non-die-cut shapes: fraction of image size
      // Level 0=0, 1=1.5%, 2=3%, 3=4.5%, 4=6%, 5=7.5% of min dimension
      var shapeBorderPx = 0;
      if (showBorder && cut !== "die-cut") {
        shapeBorderPx = Math.round(Math.min(natW, natH) * borderLevel * 0.015);
      }

      // For die-cut, calculate how much the border expands in full-res pixels
      // so we can size the canvas to fit it
      // Must account for: border expansion + 3px dilation + safety margin
      var dieBorderPx = 0;
      if (showBorder && cut === "die-cut") {
        var proofBorderMap = [0, 4, 8, 12, 18, 25];
        var traceMargin = 6;
        var traceDilation = 3;
        var estImgScale = Math.min((400 - traceMargin * 2) / natW, (400 - traceMargin * 2) / natH);
        dieBorderPx = Math.ceil(((proofBorderMap[borderLevel] || 0) + traceDilation + 2) / estImgScale) + 4;
      }

      // High-res canvas — expand for border padding
      var borderExpand = cut === "die-cut" ? dieBorderPx : shapeBorderPx;
      var totalW = natW + borderExpand * 2;
      var totalH = natH + borderExpand * 2;
      var canvas = document.createElement("canvas");
      canvas.width = totalW;
      canvas.height = totalH;
      var ctx = canvas.getContext("2d");

      if (cut === "die-cut") {
        // Trace silhouette for white backing (same algo as drawDieCutOutline)
        var traceSize = 400;
        var tmp = document.createElement("canvas");
        tmp.width = traceSize;
        tmp.height = traceSize;
        var tctx = tmp.getContext("2d");
        var margin = 6;
        var imgScale = Math.min((traceSize - margin * 2) / natW, (traceSize - margin * 2) / natH);
        var dW = natW * imgScale, dH = natH * imgScale;
        var oX = (traceSize - dW) / 2, oY = (traceSize - dH) / 2;
        tctx.drawImage(imgEl, oX, oY, dW, dH);

        var iData = tctx.getImageData(0, 0, traceSize, traceSize);
        var data = iData.data;
        var alpha = new Uint8Array(traceSize * traceSize);
        for (var i = 0; i < traceSize * traceSize; i++) {
          alpha[i] = data[i * 4 + 3] > 20 ? 1 : 0;
        }
        // Dilate 3px to close anti-aliasing gaps and extend thin features
        var dilR = 3;
        var clean = new Uint8Array(traceSize * traceSize);
        for (var y = 0; y < traceSize; y++) {
          for (var x = 0; x < traceSize; x++) {
            if (alpha[y * traceSize + x]) { clean[y * traceSize + x] = 1; continue; }
            var found = false;
            for (var dy2 = -dilR; dy2 <= dilR && !found; dy2++) {
              for (var dx2 = -dilR; dx2 <= dilR && !found; dx2++) {
                if (dx2*dx2 + dy2*dy2 > dilR*dilR) continue;
                var nx = x+dx2, ny = y+dy2;
                if (nx>=0 && ny>=0 && nx<traceSize && ny<traceSize && alpha[ny*traceSize+nx]) found = true;
              }
            }
            clean[y * traceSize + x] = found ? 1 : 0;
          }
        }

        // Boundary + centroid
        var minX=traceSize, maxX=0, minY=traceSize, maxY=0;
        for (var y=0; y<traceSize; y++) for (var x=0; x<traceSize; x++) {
          if (clean[y*traceSize+x]) {
            if (x<minX) minX=x; if (x>maxX) maxX=x;
            if (y<minY) minY=y; if (y>maxY) maxY=y;
          }
        }
        var cx = (minX+maxX)/2, cy = (minY+maxY)/2;

        var bPts = [];
        for (var y=1; y<traceSize-1; y++) for (var x=1; x<traceSize-1; x++) {
          if (!clean[y*traceSize+x]) continue;
          if (!clean[(y-1)*traceSize+x] || !clean[(y+1)*traceSize+x] || !clean[y*traceSize+(x-1)] || !clean[y*traceSize+(x+1)])
            bPts.push([x,y]);
        }

        // Bin by angle
        var nBins = 1440;
        var bins = new Array(nBins);
        for (var b=0; b<nBins; b++) bins[b] = null;
        for (var i=0; i<bPts.length; i++) {
          var pt = bPts[i];
          var ang = Math.atan2(pt[1]-cy, pt[0]-cx);
          if (ang < 0) ang += 2*Math.PI;
          var bin = Math.floor(ang/(2*Math.PI)*nBins) % nBins;
          var dist = Math.sqrt((pt[0]-cx)*(pt[0]-cx)+(pt[1]-cy)*(pt[1]-cy));
          if (!bins[bin] || dist > bins[bin][2]) bins[bin] = [pt[0], pt[1], dist];
        }
        var contour = [];
        for (var b=0; b<nBins; b++) if (bins[b]) contour.push([bins[b][0], bins[b][1]]);

        // Smooth
        function smoothPass(pts, w) {
          var out = [];
          for (var i=0; i<pts.length; i++) {
            var sx=0,sy=0,wt=0;
            for (var j=-w; j<=w; j++) {
              var idx = (i+j+pts.length) % pts.length;
              var weight = 1 - Math.abs(j)/(w+1);
              sx += pts[idx][0]*weight; sy += pts[idx][1]*weight; wt += weight;
            }
            out.push([sx/wt, sy/wt]);
          }
          return out;
        }
        contour = smoothPass(smoothPass(smoothPass(contour, 3), 2), 2);
        // Extra smoothing at higher border levels for smoother curves
        if (borderLevel >= 3) contour = smoothPass(contour, 2);
        if (borderLevel >= 5) contour = smoothPass(contour, 2);

        // Expand for white border — scales with level
        // Level 0=0, 1=4, 2=8, 3=12, 4=18, 5=25 trace-canvas pixels
        var proofBorderMap = [0, 4, 8, 12, 18, 25];
        var borderPad = showBorder ? (proofBorderMap[borderLevel] || 0) : 0;
        function expandPath(pts, amount) {
          if (amount === 0) return pts.slice();
          var out = [], n = pts.length;
          for (var i=0; i<n; i++) {
            var prev = pts[(i-1+n)%n], next = pts[(i+1)%n];
            var tx = next[0]-prev[0], ty = next[1]-prev[1];
            var tlen = Math.sqrt(tx*tx+ty*ty)||1;
            var nnx = -ty/tlen, nny = tx/tlen;
            var ddx = pts[i][0]-cx, ddy = pts[i][1]-cy;
            if (nnx*ddx + nny*ddy < 0) { nnx=-nnx; nny=-nny; }
            out.push([pts[i][0]+nnx*amount, pts[i][1]+nny*amount]);
          }
          return out;
        }
        var borderPath = expandPath(contour, borderPad);

        // Map from trace coords to full-res canvas coords (accounting for trace margins)
        // Offset by dieBorderPx so the expanded border fits within the canvas
        function drawSmooth(path) {
          ctx.beginPath();
          for (var i=0; i<path.length; i++) {
            var px = (path[i][0] - oX) / imgScale + dieBorderPx, py = (path[i][1] - oY) / imgScale + dieBorderPx;
            if (i===0) ctx.moveTo(px, py);
            else {
              var cur = path[i], nxt = path[(i+1)%path.length];
              var mx = ((cur[0]+nxt[0])/2 - oX) / imgScale + dieBorderPx, my = ((cur[1]+nxt[1])/2 - oY) / imgScale + dieBorderPx;
              ctx.quadraticCurveTo(px, py, mx, my);
            }
          }
          ctx.closePath();
        }

        // White backing fill
        if (showBorder) {
          ctx.save();
          drawSmooth(borderPath);
          ctx.fillStyle = "#fff";
          ctx.fill();
          ctx.restore();
        }

        // Draw artwork offset by dieBorderPx
        ctx.drawImage(imgEl, dieBorderPx, dieBorderPx, natW, natH);

      } else if (cut === "round") {
        // Circle clip with border padding
        var r = Math.min(totalW, totalH) / 2;
        ctx.save();
        ctx.beginPath();
        ctx.arc(totalW/2, totalH/2, r, 0, 2*Math.PI);
        ctx.closePath();
        ctx.clip();
        ctx.fillStyle = "#fff";
        ctx.fillRect(0, 0, totalW, totalH);
        ctx.drawImage(imgEl, shapeBorderPx, shapeBorderPx, natW, natH);
        ctx.restore();

      } else if (cut === "rounded-rect") {
        // Rounded rectangle clip with border padding
        var rr = Math.min(totalW, totalH) * 0.1;
        ctx.save();
        ctx.beginPath();
        ctx.moveTo(rr, 0);
        ctx.lineTo(totalW-rr, 0);
        ctx.quadraticCurveTo(totalW, 0, totalW, rr);
        ctx.lineTo(totalW, totalH-rr);
        ctx.quadraticCurveTo(totalW, totalH, totalW-rr, totalH);
        ctx.lineTo(rr, totalH);
        ctx.quadraticCurveTo(0, totalH, 0, totalH-rr);
        ctx.lineTo(0, rr);
        ctx.quadraticCurveTo(0, 0, rr, 0);
        ctx.closePath();
        ctx.clip();
        ctx.fillStyle = "#fff";
        ctx.fillRect(0, 0, totalW, totalH);
        ctx.drawImage(imgEl, shapeBorderPx, shapeBorderPx, natW, natH);
        ctx.restore();

      } else {
        // Square: white bg + artwork with border padding
        ctx.fillStyle = "#fff";
        ctx.fillRect(0, 0, totalW, totalH);
        ctx.drawImage(imgEl, shapeBorderPx, shapeBorderPx, natW, natH);
      }

      // Crop to content bounding box + 10px padding
      var cData = ctx.getImageData(0, 0, canvas.width, canvas.height);
      var cPx = cData.data;
      var bMinX = canvas.width, bMaxX = 0, bMinY = canvas.height, bMaxY = 0;
      for (var cy2 = 0; cy2 < canvas.height; cy2++) {
        for (var cx2 = 0; cx2 < canvas.width; cx2++) {
          if (cPx[(cy2 * canvas.width + cx2) * 4 + 3] > 0) {
            if (cx2 < bMinX) bMinX = cx2;
            if (cx2 > bMaxX) bMaxX = cx2;
            if (cy2 < bMinY) bMinY = cy2;
            if (cy2 > bMaxY) bMaxY = cy2;
          }
        }
      }
      var cropPad = 10;
      bMinX = Math.max(0, bMinX - cropPad);
      bMinY = Math.max(0, bMinY - cropPad);
      bMaxX = Math.min(canvas.width - 1, bMaxX + cropPad);
      bMaxY = Math.min(canvas.height - 1, bMaxY + cropPad);
      var cropW = bMaxX - bMinX + 1;
      var cropH = bMaxY - bMinY + 1;
      var croppedCanvas = document.createElement("canvas");
      croppedCanvas.width = cropW;
      croppedCanvas.height = cropH;
      croppedCanvas.getContext("2d").drawImage(canvas, bMinX, bMinY, cropW, cropH, 0, 0, cropW, cropH);

      callback(croppedCanvas);
    }

    // Track the uploaded proof filename for add-to-cart
    var proofFilename = "";

    // Add to cart – generate proof, upload, then show popup
    $("#sc-add-to-cart").on("click", function () {
      if (!uploadedFile) return;
      var sel = getSelections();
      var price = calcPrice(sel);
      var sizeObj = scData.sizes[sel.sizeIndex] || {};
      var $btn = $(this);
      $btn.prop("disabled", true).text("Generating proof...");

      var imgEl = document.getElementById("sc-preview-img");
      var natW = (imgEl && imgEl.naturalWidth) || 1;
      var natH = (imgEl && imgEl.naturalHeight) || 1;

      // Calculate printed dimensions — capped to selected size
      var sizeW = parseFloat(sizeObj.width) || 1;
      var sizeH = parseFloat(sizeObj.height) || 1;
      var printW, printH;

      // Generate proof canvas
      generateProofCanvas(sel, imgEl, function (proofCanvas) {
        // Use cropped proof dimensions for true aspect ratio
        var proofAspect = proofCanvas.width / proofCanvas.height;
        var maxSize = Math.max(sizeW, sizeH);
        if (proofAspect >= 1) {
          // Wider than tall
          printW = maxSize;
          printH = maxSize / proofAspect;
        } else {
          // Taller than wide
          printH = maxSize;
          printW = maxSize * proofAspect;
        }

        var proofDataUrl = proofCanvas.toDataURL("image/png");

        // Upload proof to server
        $.post(scData.ajax_url, {
          action: "sc_upload_proof",
          nonce: scData.nonce,
          proof_data: proofDataUrl,
        })
          .done(function (res) {
            if (!res.success) {
              alert(res.data || "Could not generate proof.");
              $btn.text("Add to Cart").prop("disabled", false);
              return;
            }

            proofFilename = res.data.filename;
            var proofUrl = res.data.url;

            // Check resolution
            var effectiveDpi = Math.min(natW / printW, natH / printH);
            if (effectiveDpi < 150) {
              $("#sc-proof-res-warning").show();
            } else {
              $("#sc-proof-res-warning").hide();
            }

            // Show overlay first so container has width
            $("#sc-proof-overlay").css("display", "flex").hide().fadeIn(200, function () {
              // Size proof image proportionally
              var $proofPreview = $(".sc-proof-preview");
              var availW = $proofPreview.innerWidth() - 80;
              var availH = $(window).height() * 0.35;
              var pScale = Math.min(availW / printW, availH / printH);
              var dispW = Math.round(printW * pScale);
              var dispH = Math.round(printH * pScale);
              $("#sc-proof-image-wrap").css({ width: dispW + "px", height: dispH + "px" });

              // Show the uploaded proof PNG
              $("#sc-proof-img").attr("src", proofUrl + "?v=" + Date.now());

              // Dimension labels (set after layout so rulers size correctly)
              $("#sc-proof-dim-w").text(printW.toFixed(2) + '"');
              $("#sc-proof-dim-h").text(printH.toFixed(2) + '"');
            });

            // Details
            var matLabel = $("#sc-material option:selected").text() || sel.material;
            var finLabel = $('input[name="sc_finish"]:checked').closest("label").text().trim() || sel.finish;
            var lamLabel = sel.laminated === "yes" ? "Yes" : "Non-Laminated";
            var cutLabel = sel.cut === "die-cut" ? "Die-Cut" : "Square";
            var borderLabel = sel.borderLevel > 0 ? "Level " + sel.borderLevel : "None";

            var rows = "";
            rows += '<div class="sc-proof-row"><span>Size</span><span>' + sizeObj.label + "</span></div>";
            rows += '<div class="sc-proof-row"><span>Printed Dimensions</span><span>' + printW.toFixed(2) + '" &times; ' + printH.toFixed(2) + '"</span></div>';
            rows += '<div class="sc-proof-row"><span>Cut</span><span>' + cutLabel + "</span></div>";
            rows += '<div class="sc-proof-row"><span>Material</span><span>' + matLabel + "</span></div>";
            rows += '<div class="sc-proof-row"><span>Finish</span><span>' + finLabel + "</span></div>";
            rows += '<div class="sc-proof-row"><span>Laminated</span><span>' + lamLabel + "</span></div>";
            rows += '<div class="sc-proof-row"><span>White Border</span><span>' + borderLabel + "</span></div>";
            rows += '<div class="sc-proof-row"><span>Quantity</span><span>' + sel.quantity + "</span></div>";
            if (price.discount > 0) {
              rows += '<div class="sc-proof-row"><span>Bulk Discount</span><span>-' + price.discount + "%</span></div>";
            }
            rows += '<div class="sc-proof-row" style="border-top:1px solid #ddd;padding-top:8px;margin-top:4px;"><span><strong>Price Each</strong></span><span><strong>$' + price.priceEach.toFixed(2) + "</strong></span></div>";
            rows += '<div class="sc-proof-row"><span><strong>Total</strong></span><span><strong>$' + price.total.toFixed(2) + "</strong></span></div>";
            $("#sc-proof-details").html(rows);

            $btn.text("Add to Cart").prop("disabled", false);
          })
          .fail(function () {
            alert("Error generating proof. Please try again.");
            $btn.text("Add to Cart").prop("disabled", false);
          });
      });
    });

    // Proof accepted – submit to cart with proof file
    $("#sc-proof-accept").on("click", function () {
      var sel = getSelections();
      var price = calcPrice(sel);
      var $btn = $("#sc-add-to-cart");
      var $acceptBtn = $(this);
      $acceptBtn.prop("disabled", true).text("Adding...");

      $.post(scData.ajax_url, {
        action: "sc_add_to_cart",
        nonce: scData.nonce,
        artwork: uploadedFilename,
        cut: sel.cut,
        size_index: sel.sizeIndex,
        material: sel.material,
        finish: sel.finish,
        laminated: sel.laminated,
        white_border: sel.whiteBorder,
        border_level: sel.borderLevel,
        quantity: sel.quantity,
        price_each: price.priceEach.toFixed(2),
        total_price: price.total.toFixed(2),
        proof_file: proofFilename,
      })
        .done(function (res) {
          if (res.success) {
            $("#sc-proof-overlay").fadeOut(200);
            $acceptBtn.prop("disabled", false).text("Accept Proof");
            $btn.text("Added!").addClass("sc-btn-success");
            if (scData.wc_active && res.data && res.data.cart_url) {
              setTimeout(function () {
                if (confirm("Added to cart! Go to cart now?")) {
                  window.location.href = res.data.cart_url;
                } else {
                  $btn.text("Add to Cart").removeClass("sc-btn-success").prop("disabled", false);
                }
              }, 500);
            } else {
              setTimeout(function () {
                $btn.text("Add to Cart").removeClass("sc-btn-success").prop("disabled", false);
              }, 2000);
            }
          } else {
            alert(res.data || "Error adding to cart.");
            $acceptBtn.prop("disabled", false).text("Accept Proof");
          }
        })
        .fail(function () {
          alert("Error. Please try again.");
          $acceptBtn.prop("disabled", false).text("Accept Proof");
        });
    });

    // Proof rejected – close modal
    $("#sc-proof-reject").on("click", function () {
      $("#sc-proof-overlay").fadeOut(200);
    });

    // Close proof overlay on backdrop click
    $("#sc-proof-overlay").on("click", function (e) {
      if (e.target === this) $(this).fadeOut(200);
    });

    updateAll();
  });
})(jQuery);
