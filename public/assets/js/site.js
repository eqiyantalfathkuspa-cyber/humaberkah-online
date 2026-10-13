(function () {
  document.documentElement.classList.add('js');

  var nav = document.getElementById('siteNav');
  if (nav) {
    var button = nav.querySelector('.nav-toggle');
    if (button) {
      button.addEventListener('click', function () {
        var open = nav.classList.toggle('open');
        button.setAttribute('aria-expanded', String(open));
      });
    }

    nav.querySelectorAll('.has-sub > a').forEach(function (link) {
      link.addEventListener('click', function (event) {
        if (window.matchMedia('(max-width: 860px)').matches && !link.parentNode.classList.contains('open')) {
          event.preventDefault();
          link.parentNode.classList.add('open');
        }
      });
    });
  }

  var ticker = document.getElementById('tick');
  if (ticker && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    var items = JSON.parse(ticker.dataset.items || '[]');
    var index = 0;
    if (items.length > 1) {
      window.setInterval(function () {
        index = (index + 1) % items.length;
        ticker.textContent = items[index];
      }, 4500);
    }
  }
})();