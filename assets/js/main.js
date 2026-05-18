/* NESTORA.my - front-end interactions */
(function () {
  'use strict';

  /* ---- Comfort Quiz multi-step ---- */
  var quiz = document.getElementById('comfortQuiz');
  if (quiz) {
    var steps = quiz.querySelectorAll('.quiz-step');
    var bar = quiz.querySelector('.quiz-progress span');
    var idx = 0;

    function show(i) {
      steps.forEach(function (s, n) { s.classList.toggle('active', n === i); });
      if (bar) bar.style.width = ((i) / (steps.length - 1) * 100) + '%';
    }

    quiz.addEventListener('click', function (ev) {
      var opt = ev.target.closest('.quiz-opt');
      if (opt) {
        var group = opt.parentElement;
        group.querySelectorAll('.quiz-opt').forEach(function (o) { o.classList.remove('sel'); });
        opt.classList.add('sel');
        var input = quiz.querySelector('input[name="' + group.dataset.name + '"]');
        if (input) input.value = opt.dataset.value;
        return;
      }
      if (ev.target.matches('[data-next]')) {
        var cur = steps[idx];
        var need = cur.querySelector('.quiz-options');
        if (need && !cur.querySelector('.quiz-opt.sel')) {
          alert('Please choose an option to continue.');
          return;
        }
        if (idx < steps.length - 1) { idx++; show(idx); }
      }
      if (ev.target.matches('[data-prev]')) {
        if (idx > 0) { idx--; show(idx); }
      }
    });

    show(0);
  }

  /* ---- Nav dropdowns (click/touch; desktop also opens on hover) ---- */
  var groups = document.querySelectorAll('.nav-group');
  groups.forEach(function (g) {
    var label = g.querySelector('.nav-group-label');
    if (!label) return;
    label.addEventListener('click', function (e) {
      e.stopPropagation();
      var wasOpen = g.classList.contains('open');
      groups.forEach(function (o) { o.classList.remove('open'); });
      if (!wasOpen) g.classList.add('open');
    });
  });
  document.addEventListener('click', function () {
    groups.forEach(function (o) { o.classList.remove('open'); });
  });

  /* ---- Confirm destructive admin actions ---- */
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('submit', function (e) {
      if (!window.confirm(el.getAttribute('data-confirm'))) e.preventDefault();
    });
  });
})();
