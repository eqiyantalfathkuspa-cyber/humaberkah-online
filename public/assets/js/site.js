(function () {
  document.documentElement.classList.add('js');

  var nav = document.getElementById('siteNav');
  if (nav) {
    var button = nav.querySelector('.nav-toggle');
    var parentLinks = Array.prototype.slice.call(nav.querySelectorAll('.has-sub > a'));

    function isSubmenuOpen(item) {
      return !item._closedByEscape
        && (item.classList.contains('open') || item.matches(':hover') || item.matches(':focus-within'));
    }

    function updateExpanded(item, link) {
      link.setAttribute('aria-expanded', isSubmenuOpen(item) ? 'true' : 'false');
    }

    parentLinks.forEach(function (link) {
      var item = link.parentNode;
      link.setAttribute('aria-haspopup', 'true');
      link.setAttribute('aria-expanded', 'false');

      item.addEventListener('mouseenter', function () {
        item._closedByEscape = false;
        updateExpanded(item, link);
      });

      item.addEventListener('mouseleave', function () {
        updateExpanded(item, link);
      });

      item.addEventListener('focusin', function () {
        if (item._escapeFocusPending) {
          item._escapeFocusPending = false;
        } else {
          item._closedByEscape = false;
        }
        updateExpanded(item, link);
      });

      item.addEventListener('focusout', function () {
        window.setTimeout(function () {
          if (!item.contains(document.activeElement)) {
            item.classList.remove('open');
            item._closedByEscape = false;
          }
          updateExpanded(item, link);
        }, 0);
      });

      link.addEventListener('click', function (event) {
        if (window.matchMedia('(max-width: 860px)').matches && !item.classList.contains('open')) {
          event.preventDefault();
          item._closedByEscape = false;
          item.classList.add('open');
          updateExpanded(item, link);
        }
      });
    });

    if (button) {
      button.addEventListener('click', function () {
        var open = nav.classList.toggle('open');
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (!open) {
          parentLinks.forEach(function (link) {
            var item = link.parentNode;
            item.classList.remove('open');
            item._closedByEscape = true;
            updateExpanded(item, link);
          });
        }
      });
    }

    document.addEventListener('click', function (event) {
      if (!nav.contains(event.target)) {
        parentLinks.forEach(function (link) {
          var item = link.parentNode;
          item.classList.remove('open');
          item._closedByEscape = false;
          updateExpanded(item, link);
        });
      }
    });

    document.addEventListener('keydown', function (event) {
      if (event.key !== 'Escape') {
        return;
      }

      var openItems = parentLinks
        .map(function (link) { return { item: link.parentNode, link: link }; })
        .filter(function (entry) { return isSubmenuOpen(entry.item); });

      if (openItems.length === 0) {
        return;
      }

      event.preventDefault();
      openItems.forEach(function (entry) {
        entry.item.classList.remove('open');
        entry.item._closedByEscape = true;
        if (document.activeElement !== entry.link) {
          entry.item._escapeFocusPending = true;
          entry.link.focus();
        }
        updateExpanded(entry.item, entry.link);
      });
    });
  }

  var carousel = document.querySelector('.home-carousel');
  if (carousel) {
    var slides = Array.prototype.slice.call(carousel.querySelectorAll('.carousel-slide'));
    var controls = Array.prototype.slice.call(carousel.querySelectorAll('[data-carousel-slide]'));
    var activeSlide = 0;
    var rotationTimer = null;
    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    function showSlide(index) {
      activeSlide = (index + slides.length) % slides.length;
      slides.forEach(function (slide, slideIndex) {
        var active = slideIndex === activeSlide;
        slide.classList.toggle('is-active', active);
        slide.setAttribute('aria-hidden', active ? 'false' : 'true');
      });
      controls.forEach(function (control, controlIndex) {
        control.setAttribute('aria-pressed', controlIndex === activeSlide ? 'true' : 'false');
      });
    }

    function stopRotation() {
      if (rotationTimer !== null) {
        window.clearInterval(rotationTimer);
        rotationTimer = null;
      }
    }

    function startRotation() {
      if (rotationTimer !== null || reducedMotion.matches || slides.length < 2) {
        return;
      }
      if (carousel.matches(':hover') || carousel.matches(':focus-within')) {
        return;
      }
      rotationTimer = window.setInterval(function () {
        showSlide(activeSlide + 1);
      }, 6000);
    }

    showSlide(0);
    carousel.classList.add('carousel-ready');
    controls.forEach(function (control) {
      control.addEventListener('click', function () {
        var target = Number(control.getAttribute('data-carousel-slide'));
        if (Number.isInteger(target) && target >= 0 && target < slides.length) {
          showSlide(target);
        }
      });
    });
    carousel.addEventListener('mouseenter', stopRotation);
    carousel.addEventListener('mouseleave', startRotation);
    carousel.addEventListener('focusin', stopRotation);
    carousel.addEventListener('focusout', function () {
      window.setTimeout(startRotation, 0);
    });

    if (typeof reducedMotion.addEventListener === 'function') {
      reducedMotion.addEventListener('change', function (event) {
        if (event.matches) {
          stopRotation();
        } else {
          startRotation();
        }
      });
    } else if (typeof reducedMotion.addListener === 'function') {
      reducedMotion.addListener(function (event) {
        if (event.matches) {
          stopRotation();
        } else {
          startRotation();
        }
      });
    }
    startRotation();
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