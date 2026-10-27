/* Dependent vehicle selects: brand -> model -> production year. */
(function () {
  'use strict';

  var AP = window.AP;
  var u = AP.utils;

  function fill(select, placeholder, items) {
    select.innerHTML = '<option value="">' + placeholder + '</option>' + items.map(function (it) {
      return '<option value="' + it.value + '">' + u.escape(it.label) + '</option>';
    }).join('');
    select.disabled = items.length === 0;
  }

  /**
   * form must contain selects named brand, model and year.
   * onSubmit receives { brand, model, year } once all three are valid.
   */
  AP.VehicleSelector = function (form, onSubmit) {
    var brand = form.elements.brand, model = form.elements.model, year = form.elements.year;
    var error = form.querySelector('.form-error');

    fill(brand, 'برند خودرو را انتخاب کنید', AP.data.vehicles.map(function (b) { return { value: b.slug, label: b.name }; }));
    fill(model, 'ابتدا برند را انتخاب کنید', []);
    fill(year, 'ابتدا مدل را انتخاب کنید', []);

    function selectedModel() {
      var found = AP.catalog.model(model.value);
      return found && found.brand.slug === brand.value ? found.model : null;
    }

    brand.addEventListener('change', function () {
      var b = AP.data.vehicles.filter(function (x) { return x.slug === brand.value; })[0];
      fill(model, 'مدل خودرو', b ? b.models.map(function (m) { return { value: m.slug, label: m.name }; }) : []);
      fill(year, 'ابتدا مدل را انتخاب کنید', []);
      if (error) error.textContent = '';
    });

    model.addEventListener('change', function () {
      var m = selectedModel();
      var years = [];
      if (m) for (var y = m.years[1]; y >= m.years[0]; y--) years.push({ value: y, label: u.faDigits(y) });
      fill(year, 'سال تولید', years);
      if (error) error.textContent = '';
    });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var m = selectedModel();
      var y = Number(year.value);
      var msg = !brand.value ? 'لطفاً برند خودرو را انتخاب کنید.'
        : !m ? 'لطفاً مدل خودرو را انتخاب کنید.'
          : !y || y < m.years[0] || y > m.years[1] ? 'لطفاً سال تولید را انتخاب کنید.' : '';
      if (error) error.textContent = msg;
      if (msg) {
        (!brand.value ? brand : !m ? model : year).focus();
        return;
      }
      onSubmit({ brand: brand.value, model: m.slug, modelName: m.name, year: y });
    });

    return {
      set: function (modelSlug) {
        var found = AP.catalog.model(modelSlug);
        if (!found) return;
        brand.value = found.brand.slug;
        brand.dispatchEvent(new Event('change'));
        model.value = found.model.slug;
        model.dispatchEvent(new Event('change'));
      }
    };
  };
})();
