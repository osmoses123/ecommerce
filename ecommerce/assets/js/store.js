(function () {
  function pad(n) {
    return String(n).padStart(2, '0');
  }

  function money(amount) {
    return '₦' + Math.round(amount).toLocaleString('en-NG');
  }

  function csrf() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  function tickCountdown() {
    var el = document.getElementById('flash-countdown');
    if (!el) return;
    var end = new Date();
    end.setHours(23, 59, 59, 999);
    function render() {
      var diff = Math.max(0, end - new Date());
      var h = Math.floor(diff / 3600000);
      var m = Math.floor((diff % 3600000) / 60000);
      var s = Math.floor((diff % 60000) / 1000);
      el.innerHTML =
        '<span class="countdown__box">' + pad(h) + '</span>:' +
        '<span class="countdown__box">' + pad(m) + '</span>:' +
        '<span class="countdown__box">' + pad(s) + '</span>';
    }
    render();
    setInterval(render, 1000);
  }

  function bindSearch() {
    var input = document.getElementById('q');
    var box = document.getElementById('search-suggest');
    if (!input || !box) return;
    var url = input.getAttribute('data-search-url');
    var timer = null;
    input.addEventListener('input', function () {
      var q = input.value.trim();
      clearTimeout(timer);
      if (q.length < 2) {
        box.hidden = true;
        box.innerHTML = '';
        return;
      }
      timer = setTimeout(function () {
        fetch(url + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            var items = data.items || [];
            if (!items.length) {
              box.innerHTML = '<div style="padding:.8rem 1rem;color:#5C6570">No matches</div>';
              box.hidden = false;
              return;
            }
            box.innerHTML = items.map(function (item) {
              return '<a href="' + item.url + '"><img src="' + item.image + '" alt=""><span><strong>' +
                escapeHtml(item.name) + '</strong><small>' + escapeHtml(item.category || '') +
                '</small></span><span class="price">' + escapeHtml(item.price) + '</span></a>';
            }).join('');
            box.hidden = false;
          })
          .catch(function () { box.hidden = true; });
      }, 220);
    });
    document.addEventListener('click', function (e) {
      if (!box.contains(e.target) && e.target !== input) box.hidden = true;
    });
  }

  function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, function (ch) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
    });
  }

  function bindWishlist() {
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-wish]');
      if (!btn) return;
      e.preventDefault();
      var body = new URLSearchParams();
      body.set('product_id', btn.getAttribute('data-wish'));
      body.set('csrf_token', csrf());
      var wishUrl = document.body.getAttribute('data-wish-url') || 'api/wishlist.php';
      fetch(wishUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json', 'X-CSRF-Token': csrf() },
        body: body.toString()
      }).then(function (r) { return r.json(); }).then(function (data) {
        if (!data.ok) return;
        document.querySelectorAll('[data-wish="' + btn.getAttribute('data-wish') + '"]').forEach(function (el) {
          el.classList.toggle('is-on', !!data.wished);
          el.setAttribute('aria-pressed', data.wished ? 'true' : 'false');
        });
        var badge = document.getElementById('wish-count');
        if (badge) {
          badge.textContent = String(data.count);
          badge.hidden = data.count < 1;
        }
      }).catch(function () {});
    });
  }

  function bindSlider() {
    var root = document.querySelector('[data-slider]');
    if (!root) return;
    var slides = Array.prototype.slice.call(root.querySelectorAll('.hero-slide'));
    var dots = Array.prototype.slice.call(root.querySelectorAll('.hero-slider__dots button'));
    if (slides.length < 2) return;
    var index = 0;
    function show(n) {
      index = (n + slides.length) % slides.length;
      slides.forEach(function (slide, i) { slide.classList.toggle('is-active', i === index); });
      dots.forEach(function (dot, i) { dot.classList.toggle('is-active', i === index); });
    }
    var prev = root.querySelector('.hero-slider__prev');
    var next = root.querySelector('.hero-slider__next');
    if (prev) prev.addEventListener('click', function () { show(index - 1); });
    if (next) next.addEventListener('click', function () { show(index + 1); });
    dots.forEach(function (dot, i) { dot.addEventListener('click', function () { show(i); }); });
    var timer = setInterval(function () { show(index + 1); }, 5000);
    root.addEventListener('mouseenter', function () { clearInterval(timer); });
    root.addEventListener('mouseleave', function () {
      timer = setInterval(function () { show(index + 1); }, 5000);
    });
  }

  function bindZoom() {
    var stage = document.getElementById('zoom-stage');
    var img = document.getElementById('pdp-photo');
    var lens = document.getElementById('zoom-lens');
    if (!stage || !img || !lens) return;
    function move(e) {
      var rect = stage.getBoundingClientRect();
      var x = e.clientX - rect.left;
      var y = e.clientY - rect.top;
      if (x < 0 || y < 0 || x > rect.width || y > rect.height) {
        lens.hidden = true;
        return;
      }
      lens.hidden = false;
      var size = 140;
      lens.style.left = Math.max(0, Math.min(rect.width - size, x - size / 2)) + 'px';
      lens.style.top = Math.max(0, Math.min(rect.height - size, y - size / 2)) + 'px';
      var zoom = 2.4;
      lens.style.backgroundImage = 'url("' + img.src + '")';
      lens.style.backgroundSize = (rect.width * zoom) + 'px ' + (rect.height * zoom) + 'px';
      lens.style.backgroundPosition = ((x / rect.width) * 100) + '% ' + ((y / rect.height) * 100) + '%';
    }
    stage.addEventListener('mousemove', move);
    stage.addEventListener('mouseleave', function () { lens.hidden = true; });
    document.querySelectorAll('.pdp-thumb').forEach(function (thumb) {
      thumb.addEventListener('click', function () {
        img.src = thumb.getAttribute('data-src');
        document.querySelectorAll('.pdp-thumb').forEach(function (el) { el.classList.remove('is-active'); });
        thumb.classList.add('is-active');
      });
    });
  }

  function bindVariants() {
    var dataEl = document.getElementById('variant-data');
    var form = document.getElementById('pdp-form');
    if (!dataEl || !form) return;
    var variants = [];
    try { variants = JSON.parse(dataEl.textContent || '[]'); } catch (e) { return; }
    var picks = form.querySelectorAll('[data-variant]');
    var hidden = document.getElementById('variant_id');
    var priceEl = document.getElementById('pdp-price');
    var stockEl = document.getElementById('pdp-stock');
    var miss = document.getElementById('variant-miss');
    var qty = document.getElementById('qty');
    var buttons = form.querySelectorAll('button[type="submit"]');
    function money(n) {
      return '₦' + Math.round(n).toLocaleString('en-NG');
    }
    function current() {
      var chosen = {};
      picks.forEach(function (sel) { chosen[sel.getAttribute('data-variant')] = Number(sel.value || 0); });
      return variants.find(function (row) {
        return picks.every(function (sel) {
          var kind = sel.getAttribute('data-variant');
          var want = Number(sel.value || 0);
          return want > 0 && row[kind] === want;
        });
      }) || null;
    }
    function paint() {
      var row = current();
      var ready = !!row;
      if (hidden) hidden.value = row ? String(row.id) : '';
      buttons.forEach(function (btn) { btn.disabled = !ready || (row && row.stock < 1); });
      if (miss) miss.hidden = ready || Array.prototype.every.call(picks, function (sel) { return !sel.value; });
      if (!row) {
        if (stockEl) { stockEl.textContent = 'Choose options'; stockEl.style.color = ''; }
        return;
      }
      if (priceEl) {
        var old = priceEl.querySelector('.price-old');
        priceEl.textContent = money(row.price) + ' ';
        if (old) priceEl.appendChild(old);
      }
      if (stockEl) {
        stockEl.textContent = row.stock > 0 ? row.stock + ' in stock' : 'Out of stock';
        stockEl.style.color = row.stock > 0 ? 'var(--success)' : 'var(--deal)';
      }
      if (qty) {
        qty.max = String(Math.max(1, row.stock));
        if (Number(qty.value) > row.stock) qty.value = String(Math.max(1, row.stock));
      }
    }
    picks.forEach(function (sel) { sel.addEventListener('change', paint); });
    paint();
  }

  function paintCart(data) {
    var badge = document.getElementById('cart-count');
    var panel = document.getElementById('cart-panel');
    if (badge) {
      var count = Number(data.count || 0);
      badge.textContent = String(count);
      badge.hidden = count < 1;
    }
    if (!panel) return;
    var lines = data.lines || [];
    if (!lines.length) {
      panel.innerHTML = '<p class="cart-panel__empty">Your basket is empty.</p><a class="btn btn-ghost btn-sm btn-block" href="' + escapeHtml(data.cart_url || 'cart.php') + '">View cart</a>';
      return;
    }
    var html = '<p class="cart-panel__title">Your basket</p><ul class="cart-panel__list">';
    lines.forEach(function (line) {
      html += '<li><img src="' + escapeHtml(line.image) + '" alt=""><span><a href="' + escapeHtml(line.url) + '">' + escapeHtml(line.name) + '</a>';
      if (line.variant) html += '<small>' + escapeHtml(line.variant) + '</small>';
      html += '<small>' + line.qty + ' × ' + escapeHtml(line.price) + '</small></span><strong>' + escapeHtml(line.total) + '</strong></li>';
    });
    html += '</ul><div class="cart-panel__sum"><span>Subtotal</span><strong>' + escapeHtml(data.subtotal) + '</strong></div>';
    html += '<div class="cart-panel__actions"><a class="btn btn-ghost btn-sm" href="' + escapeHtml(data.cart_url) + '">View cart</a>';
    html += '<a class="btn btn-brand btn-sm" href="' + escapeHtml(data.checkout_url) + '">Checkout</a></div>';
    panel.innerHTML = html;
  }

  function loadCartPanel() {
    var url = document.body.getAttribute('data-cart-summary');
    if (!url) return;
    fetch(url, { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(paintCart)
      .catch(function () {});
  }

  function flashToast(message, ok) {
    var old = document.getElementById('cart-toast');
    if (old) old.remove();
    var el = document.createElement('div');
    el.id = 'cart-toast';
    el.className = 'cart-toast' + (ok ? '' : ' cart-toast--error');
    el.textContent = message;
    document.body.appendChild(el);
    setTimeout(function () { el.remove(); }, 2400);
  }

  function postCart(form, btn) {
    var endpoint = document.body.getAttribute('data-cart-url') || form.getAttribute('action');
    var body = new URLSearchParams(new FormData(form));
    body.set('ajax', '1');
    body.set('action', 'cart');
    if (btn) btn.disabled = true;
    fetch(endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        Accept: 'application/json',
        'X-CSRF-Token': csrf()
      },
      body: body.toString()
    }).then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
      .then(function (res) {
        paintCart(res.data || {});
        flashToast((res.data && res.data.message) || (res.ok ? 'Added to cart.' : 'Could not add to cart.'), !!(res.data && res.data.ok));
        if (res.data && res.data.ok) {
          var fly = document.getElementById('cart-flyout');
          if (fly) {
            fly.classList.add('is-open');
            setTimeout(function () { fly.classList.remove('is-open'); }, 1800);
          }
        }
      })
      .catch(function () { flashToast('Could not add to cart.', false); })
      .finally(function () { if (btn) btn.disabled = false; });
  }

  function bindAjaxCart() {
    document.addEventListener('submit', function (e) {
      var form = e.target;
      if (!form.classList || !form.classList.contains('product-card__cart')) return;
      e.preventDefault();
      postCart(form, form.querySelector('button[type=submit]'));
    });
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('#pdp-form button[value=cart]');
      if (!btn) return;
      e.preventDefault();
      var form = btn.closest('form');
      if (!form) return;
      postCart(form, btn);
    });
  }

  function bindCartPanel() {
    var fly = document.getElementById('cart-flyout');
    var panel = document.getElementById('cart-panel');
    if (!fly || !panel) return;
    var hideTimer = null;
    function show() {
      clearTimeout(hideTimer);
      fly.classList.add('is-open');
      loadCartPanel();
    }
    function hide() {
      hideTimer = setTimeout(function () {
        fly.classList.remove('is-open');
      }, 180);
    }
    fly.addEventListener('mouseenter', show);
    fly.addEventListener('mouseleave', hide);
    fly.addEventListener('focusin', show);
    fly.addEventListener('focusout', function (e) {
      if (!fly.contains(e.relatedTarget)) hide();
    });
    loadCartPanel();
  }

  function bindCheckout() {
    var form = document.getElementById('checkout-form');
    if (!form) return;
    var country = document.getElementById('country_id');
    var state = document.getElementById('state_id');
    var city = document.getElementById('city_id');
    var shipEl = document.getElementById('ship-amount');
    var totalEl = document.getElementById('grand-total');
    var subtotal = Number(form.getAttribute('data-subtotal') || 0);
    var alwaysFree = form.getAttribute('data-free') === '1';
    var endpoint = form.getAttribute('data-locations');
    var account = document.getElementById('create_account');
    var accountFields = document.getElementById('account-fields');

    function paintShipping() {
      var opt = state.options[state.selectedIndex];
      var fee = opt && opt.getAttribute('data-fee') ? Number(opt.getAttribute('data-fee')) : 0;
      var shipping = alwaysFree ? 0 : fee;
      if (shipEl) shipEl.textContent = shipping === 0 ? 'Free' : money(shipping);
      if (totalEl) totalEl.textContent = money(subtotal + shipping);
    }

    function fillSelect(select, rows, placeholder) {
      var current = select.value;
      select.innerHTML = '';
      var first = document.createElement('option');
      first.value = '';
      first.textContent = placeholder;
      select.appendChild(first);
      rows.forEach(function (row) {
        var opt = document.createElement('option');
        opt.value = row.id;
        opt.textContent = row.name;
        if (row.shipping_fee !== undefined) opt.setAttribute('data-fee', row.shipping_fee);
        select.appendChild(opt);
      });
      if (current) select.value = current;
    }

    function loadStates() {
      fetch(endpoint + '?country_id=' + encodeURIComponent(country.value))
        .then(function (r) { return r.json(); })
        .then(function (data) {
          fillSelect(state, data.states || [], 'Select state');
          fillSelect(city, [], 'Select city');
          paintShipping();
        });
    }

    function loadCities() {
      if (!state.value) {
        fillSelect(city, [], 'Select city');
        paintShipping();
        return;
      }
      fetch(endpoint + '?state_id=' + encodeURIComponent(state.value))
        .then(function (r) { return r.json(); })
        .then(function (data) {
          fillSelect(city, data.cities || [], 'Select city');
          paintShipping();
        });
    }

    if (country) country.addEventListener('change', loadStates);
    if (state) state.addEventListener('change', function () { loadCities(); });
    if (account && accountFields) {
      account.addEventListener('change', function () {
        accountFields.hidden = !account.checked;
      });
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    tickCountdown();
    bindSearch();
    bindWishlist();
    bindSlider();
    bindZoom();
    bindVariants();
    bindCheckout();
    bindAjaxCart();
    bindCartPanel();
  });
})();
