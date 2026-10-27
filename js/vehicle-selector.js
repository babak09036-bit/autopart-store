/* Dependent vehicle selects: brand → model → production year. Data comes from the API (vehicle_brands / vehicle_models). */
(function () {
  'use strict';

  var AP = window.AP;
  var u = AP.utils;

  function fill(select, placeholder, items) {
    select.innerHTML = '<option value="">' + placeholder + '</option>' + items.map(function (it) {
      return '<option value="' + u.escape(it.value) + '">' + u.escape(it.label) + '</option>';
    }).join('');
    select.disabled = items.length === 0;
  }

  /** form needs selects named brand, model, year. onSubmit({brand, model, modelName, year}). */
  AP.VehicleSelector = function (form, vehicles, onSubmit) {
    var brand = form.elements.brand, model = form.elements.model, year = form.elements.year;
    var error = form.querySelector('.form-error');
    var current = null;

    fill(brand, 'برند خودرو را انتخاب کنید', vehicles.map(function (b) { return { value: b.slug, label: b.name }; }));
    fill(model, 'ابتدا برند را انتخاب کنید', []);
    fill(year, 'ابتدا مدل را انتخاب کنید', []);

    function brandData() { return vehicles.filter(function (b) { return b.slug === brand.value; })[0]; }

    brand.addEventListener('change', function () {
      var b = brandData();
      fill(model, 'مدل خودرو', b ? b.models.map(function (m) { return { value: m.slug, label: m.name }; }) : []);
      fill(year, 'ابتدا مدل را انتخاب کنید', []);
      current = null;
      if (error) error.textContent = '';
    });

    model.addEventListener('change', function () {
      var b = brandData();
      current = b ? b.models.filter(function (m) { return m.slug === model.value; })[0] : null;
      var years = [];
      if (current) for (var y = current.years[1]; y >= current.years[0]; y--) years.push({ value: y, label: u.faDigits(y) });
      fill(year, 'سال تولید', years);
      if (error) error.textContent = '';
    });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var y = Number(year.value);
      var msg = !brand.value ? 'لطفاً برند خودرو را انتخاب کنید.' : !current ? 'لطفاً مدل خودرو را انتخاب کنید.'
        : !y || y < current.years[0] || y > current.years[1] ? 'لطفاً سال تولید را انتخاب کنید.' : '';
      if (error) error.textContent = msg;
      if (msg) {
        (!brand.value ? brand : !current ? model : year).focus();
        return;
      }
      onSubmit({ brand: brand.value, model: current.slug, modelName: current.name, year: y });
    });
  };
})();
