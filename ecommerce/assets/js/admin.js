(function () {
  function bindAppearancePreview() {
    var titleInput = document.getElementById('site_title');
    var colorInput = document.getElementById('primary_color');
    var colorText = document.getElementById('primary_color_hex');
    var previewTitle = document.getElementById('preview-title');
    var previewStrip = document.getElementById('preview-strip');
    var logoInput = document.getElementById('logo');
    var previewLogo = document.getElementById('preview-logo');

    if (!previewStrip) return;

    function syncColor(val) {
      if (!val) return;
      if (colorInput && colorInput.value !== val) colorInput.value = val;
      if (colorText && colorText.value !== val) colorText.value = val;
      previewStrip.style.setProperty('--brand', val);
      var searchBtn = previewStrip.querySelector('.preview-search span');
      if (searchBtn) searchBtn.style.background = val;
    }

    if (titleInput && previewTitle) {
      titleInput.addEventListener('input', function () {
        previewTitle.textContent = titleInput.value || 'Shopora';
      });
    }
    if (colorInput) {
      colorInput.addEventListener('input', function () {
        syncColor(colorInput.value);
      });
    }
    if (colorText) {
      colorText.addEventListener('input', function () {
        var v = colorText.value.trim();
        if (/^#[0-9A-Fa-f]{6}$/.test(v)) syncColor(v);
      });
    }
    if (logoInput && previewLogo) {
      logoInput.addEventListener('change', function () {
        var file = logoInput.files && logoInput.files[0];
        if (!file) return;
        var url = URL.createObjectURL(file);
        previewLogo.src = url;
        previewLogo.hidden = false;
      });
    }
  }

  function bindEditor() {
    var root = document.querySelector('[data-rte]');
    if (!root) return;
    var area = root.querySelector('.rte-area');
    var field = root.querySelector('textarea');
    var form = document.getElementById('product-form');
    if (!area || !field) return;
    var raw = field.value || '';
    if (!/<[a-z]/i.test(raw)) {
      area.innerHTML = raw
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/\n/g, '<br>');
    } else {
      area.innerHTML = raw;
    }
    field.hidden = true;
    root.querySelectorAll('[data-cmd]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var cmd = btn.getAttribute('data-cmd');
        area.focus();
        if (cmd === 'link') {
          var href = window.prompt('Link URL (https://)');
          if (!href || !/^https?:\/\//i.test(href)) return;
          document.execCommand('createLink', false, href);
          return;
        }
        document.execCommand(cmd, false, null);
      });
    });
    var block = root.querySelector('[data-block]');
    if (block) {
      block.addEventListener('change', function () {
        area.focus();
        document.execCommand('formatBlock', false, block.value);
      });
    }
    if (form) {
      form.addEventListener('submit', function () {
        field.value = area.innerHTML;
      });
    }
  }

  function bindSlug() {
    var name = document.getElementById('name');
    var slug = document.getElementById('slug');
    if (!name || !slug || slug.getAttribute('data-autofill') !== '1') return;
    var touched = slug.value.trim() !== '';
    slug.addEventListener('input', function () { touched = true; });
    name.addEventListener('input', function () {
      if (touched) return;
      slug.value = name.value.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    });
  }

  function bindOfferRows() {
    function wire(listId, buttonId) {
      var list = document.getElementById(listId);
      var button = document.getElementById(buttonId);
      if (!list || !button) return;
      list.addEventListener('click', function (e) {
        var remove = e.target.closest('.row-remove');
        if (!remove) return;
        var rows = list.querySelectorAll('.offer-row');
        if (rows.length < 2) {
          rows[0].querySelectorAll('input').forEach(function (input) {
            input.value = input.type === 'number' && input.name.indexOf('stock') !== -1 ? '0' : (input.name.indexOf('tier_min') !== -1 ? '2' : '');
          });
          rows[0].querySelectorAll('select').forEach(function (sel) { sel.selectedIndex = 0; });
          return;
        }
        remove.closest('.offer-row').remove();
      });
      button.addEventListener('click', function () {
        var row = list.querySelector('.offer-row');
        if (!row) return;
        var copy = row.cloneNode(true);
        copy.querySelectorAll('input').forEach(function (input) {
          if (input.name.indexOf('tier_min') !== -1) input.value = '2';
          else if (input.name.indexOf('tier_value') !== -1 || input.name.indexOf('var_stock') !== -1) input.value = '0';
          else input.value = '';
        });
        copy.querySelectorAll('select').forEach(function (sel) { sel.selectedIndex = 0; });
        list.appendChild(copy);
      });
    }
    wire('tier-list', 'add-tier');
    wire('variant-list', 'add-variant');
  }

  function bindDataTables() {
    if (!window.jQuery || !jQuery.fn.dataTable) return;
    jQuery('table.data').each(function () {
      var table = jQuery(this);
      if (table.find('tbody tr').length < 1) return;
      if (table.find('thead th').length < 2) return;
      if (table.hasClass('dataTable')) return;
      var colCount = table.find('thead th').length;
      var orderable = [];
      for (var i = 0; i < colCount; i++) {
        var heading = (table.find('thead th').eq(i).text() || '').trim();
        var last = i === colCount - 1 && (heading === '' || /action|edit|delete/i.test(heading));
        var firstImage = i === 0 && table.find('tbody td').eq(0).find('img').length > 0;
        orderable.push({ orderable: !(last || firstImage), searchable: !(last || firstImage) });
      }
      table.DataTable({
        pageLength: 25,
        order: [],
        columnDefs: orderable.map(function (cfg, idx) {
          return { targets: idx, orderable: cfg.orderable, searchable: cfg.searchable };
        }),
        language: {
          search: 'Filter:',
          lengthMenu: 'Show _MENU_',
          info: '_START_–_END_ of _TOTAL_',
          infoEmpty: 'No rows',
          zeroRecords: 'No matching rows'
        }
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    bindAppearancePreview();
    bindEditor();
    bindSlug();
    bindOfferRows();
    bindDataTables();
  });
})();
