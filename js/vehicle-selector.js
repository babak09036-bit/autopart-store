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

  /** The visitor's chosen vehicle, remembered in this browser only ({brand, model, modelName, year}). */
  AP.garage = {
    get: function () { try { return JSON.parse(localStorage.getItem('ap_vehicle') || 'null'); } catch (e) { return null; } },
    set: function (v) { try { localStorage.setItem('ap_vehicle', JSON.stringify(v)); } catch (e) { /* storage unavailable */ } }
  };

  /** form needs selects named brand, model, year. onSubmit({brand, model, modelName, year}). initial preselects a saved vehicle. */
  AP.VehicleSelector = function (form, vehicles, onSubmit, initial) {
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
      var sel = { brand: brand.value, model: current.slug, modelName: (brandData() || {}).name + ' ' + current.name, year: y };
      AP.garage.set(sel);
      onSubmit(sel);
    });

    if (initial && initial.brand) {
      brand.value = initial.brand;
      if (brand.value) {
        brand.dispatchEvent(new Event('change'));
        model.value = initial.model;
        if (model.value) { model.dispatchEvent(new Event('change')); year.value = String(initial.year || ''); }
      }
    }
  };
})();
