(function(){
  document.addEventListener('click', function(e){
    var c = e.target.closest('[data-copy]');
    if (c) {
      e.preventDefault();
      var t = c.getAttribute('data-copy');
      var done = function(){ var o = c.innerHTML; c.innerHTML = 'Copied ✓'; setTimeout(function(){ c.innerHTML = o; }, 1400); };
      if (navigator.clipboard) navigator.clipboard.writeText(t).then(done, function(){ prompt('Copy this:', t); });
      else prompt('Copy this:', t);
    }
    var f = e.target.closest('[data-fill]');
    if (f) {
      e.preventDefault();
      var data = JSON.parse(f.getAttribute('data-fill'));
      Object.keys(data).forEach(function(k){
        var el = document.querySelector('[name="'+k+'"]');
        if (el && el.type !== 'file') el.value = data[k];
      });
    }
    if (e.target.closest('[data-menu]')) document.body.classList.toggle('nav-open');
    var amt = e.target.closest('[data-amount]');
    if (amt) { var i = document.querySelector(amt.getAttribute('data-target') || '#amount'); if (i) i.value = amt.getAttribute('data-amount'); }
  });
  document.addEventListener('change', function(e){
    if (e.target.matches('.drop input[type=file]')) {
      var n = e.target.closest('.drop').querySelector('.fname');
      if (n) n.textContent = e.target.files.length ? '📎 ' + e.target.files[0].name : '';
    }
  });
  document.querySelectorAll('form[data-busy]').forEach(function(f){
    f.addEventListener('submit', function(){
      var b = f.querySelector('button[type=submit]');
      if (b) { b.disabled = true; b.innerHTML = '⏳ ' + (f.getAttribute('data-busy') || 'Working…'); }
    });
  });
  setTimeout(function(){ document.querySelectorAll('.toast').forEach(function(t){ t.style.transition='.4s'; t.style.opacity=0; setTimeout(function(){ t.remove(); }, 400); }); }, 5000);
})();
