(function ($) {
  "use strict";

  let uploadedFile = null;
  let uploadedFilename = "";
  let fileIsVector = false; // PDF, AI, PSD, SVG — no pixel preview

  /* ── Helpers ── */
  function getSelections() {
    var lam = $('input[name="sc_laminated"]:checked').val() || "yes";
    // Force non-laminated when lamination is globally disabled
    if (!scData.lamination_enabled) lam = "no";
    var minQty = parseInt(scData.min_quantity, 10) || 1;
    return {
      cut: $('input[name="sc_cut"]:checked').val() || "square",
      sizeIndex: parseInt($("#sc-size").val(), 10) || 0,
      material: $("#sc-material").val() || "vinyl",
      finish: $('input[name="sc_finish"]:checked').val() || "glossy",
      laminated: lam,
      whiteBorder: $('input[name="sc_white_border"]:checked').val() || "yes",
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

    if (sel.cut === "die-cut") basePrice *= 1.15;

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
      } else {
        $img.show();
        $placeholder.hide();
        $art.show();

        // Toggle white border visibility
        if (sel.whiteBorder === "yes") {
          $art.addClass("sc-border-on").removeClass("sc-border-off");
        } else {
          $art.addClass("sc-border-off").removeClass("sc-border-on");
        }

        if (sel.cut === "die-cut") {
          drawDieCutOutline();
        } else {
          $("#sc-diecut-canvas").hide();
        }
      }
    } else {
      $img.hide();
      $placeholder.text("Upload artwork to see preview").show();
      $art.hide();
      $("#sc-diecut-canvas").hide();
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
    const artPos = $art.position();

    canvas.width = $container.width();
    canvas.height = $container.height();
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
      ctx.strokeRect(artPos.left - p, artPos.top - p, artW + p * 2, artH + p * 2);
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

    // White border expansion amount (in temp canvas pixels)
    const borderPad = showBorder ? 6 : 0;
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
    const cutLinePath = expandPath(smoothed, linePad);

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
    const resampledCut = resample(cutLinePath, 500);

    // Map to container coordinates
    const scaleX = artW / size;
    const scaleY = artH / size;
    const ox = artPos.left;
    const oy = artPos.top;

    function toScreen(pts) {
      return pts.map(function(p) { return [ox + p[0] * scaleX, oy + p[1] * scaleY]; });
    }

    const screenBorder = toScreen(resampledBorder);
    const screenCut = toScreen(resampledCut);

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

    // 2) Dashed cut line
    ctx.save();
    drawSmoothPath(screenCut);
    ctx.setLineDash([7, 4]);
    ctx.strokeStyle = "#000";
    ctx.lineWidth = 1.5;
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
    $("#sc-price-lam").text(sel.laminated === "yes" ? "Laminated" : "None");
    $("#sc-price-cut").text(
      sel.cut === "die-cut"
        ? "Die Cut (+15%)"
        : sel.cut.replace("-", " ").replace(/\b\w/g, (c) => c.toUpperCase())
    );
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
  }

  function updateAll() {
    updatePreview();
    updatePricing();
  }

  /* ── Auto-remove background for non-transparent raster images ── */
  function autoRemoveBackground() {
    if (!uploadedFilename) return;
    var $status = $("#sc-bg-status");
    var $btn = $("#sc-remove-bg");
    $btn.prop("disabled", true).text("Auto-removing background...");
    $status.show().text("Non-transparent image detected — removing background...");

    $.post(scData.ajax_url, {
      action: "sc_remove_background",
      nonce: scData.nonce,
      filename: uploadedFilename,
    })
      .done(function (res) {
        if (res.success) {
          uploadedFile = res.data.url;
          uploadedFilename = res.data.filename;
          var bust = "?v=" + Date.now();
          $("#sc-uploaded-img").attr("src", res.data.url + bust);
          $("#sc-preview-img").attr("src", res.data.url + bust);
          $status.text("Background removed automatically!");
          setTimeout(function () { $status.fadeOut(); }, 3000);
          updateAll();
        } else {
          $status.text("Auto background removal failed. You can try manually.");
        }
      })
      .fail(function () {
        $status.text("Auto background removal failed. You can try manually.");
      })
      .always(function () {
        $btn.prop("disabled", false).text("Remove Background");
      });
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

          // Auto background removal for non-transparent raster images
          if (res.data.is_raster && !res.data.has_transparency) {
            autoRemoveBackground();
          }
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
    // Hide lamination option when globally disabled
    if (!scData.lamination_enabled) {
      $(".sc-lamination-group").hide();
      // Force to non-laminated
      $('input[name="sc_laminated"][value="no"]').prop("checked", true);
      $(".sc-lamination-options .sc-lam-option").removeClass("sc-selected");
      $(".sc-lamination-options .sc-lam-option[data-lam='no']").addClass("sc-selected");
    }

    // Enforce minimum quantity
    var minQty = parseInt(scData.min_quantity, 10) || 1;
    $("#sc-quantity").attr("min", minQty);
    if (parseInt($("#sc-quantity").val(), 10) < minQty) {
      $("#sc-quantity").val(minQty);
    }

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
      fileIsVector = false;
      $("#sc-uploaded-img").attr("src", "");
      $("#sc-preview-img").attr("src", "");
      $("#sc-upload-preview-wrap").hide();
      $("#sc-upload-zone").show();
      $("#sc-file-input").val("");
      $("#sc-remove-bg").show();
      updateAll();
    });

    // Manual remove background
    $("#sc-remove-bg").on("click", function () {
      if (!uploadedFilename) return;
      var $btn = $(this);
      var $status = $("#sc-bg-status");
      $btn.prop("disabled", true).text("Processing...");
      $status.show().text("Removing background — this may take a moment...");

      $.post(scData.ajax_url, {
        action: "sc_remove_background",
        nonce: scData.nonce,
        filename: uploadedFilename,
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
        });
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

    $("#sc-size, #sc-material").on("change", updateAll);
    $("#sc-quantity").on("input change", function () {
      var minQty = parseInt(scData.min_quantity, 10) || 1;
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

    // Add to cart
    $("#sc-add-to-cart").on("click", function () {
      if (!uploadedFile) return;
      var sel = getSelections();
      var price = calcPrice(sel);
      var $btn = $(this);
      $btn.prop("disabled", true).text("Adding...");

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
        quantity: sel.quantity,
        price_each: price.priceEach.toFixed(2),
        total_price: price.total.toFixed(2),
      })
        .done(function (res) {
          if (res.success) {
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
            $btn.text("Add to Cart").prop("disabled", false);
          }
        })
        .fail(function () {
          alert("Error. Please try again.");
          $btn.text("Add to Cart").prop("disabled", false);
        });
    });

    updateAll();
  });
})(jQuery);
