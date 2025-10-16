(function () {
  "use strict";

  document.addEventListener('DOMContentLoaded', function () {

    // ✅ Fonction mobileNavToogle définie ici pour éviter les erreurs de portée
    function mobileNavToogle() {
      document.querySelector('body').classList.toggle('mobile-nav-active');
      const btn = document.querySelector('.mobile-nav-toggle');
      if (btn) {
        btn.classList.toggle('bi-list');
        btn.classList.toggle('bi-x');
      }
    }

    // ✅ Toggle du bouton mobile
    const mobileNavToggleBtn = document.querySelector('.mobile-nav-toggle');
    if (mobileNavToggleBtn) {
      mobileNavToggleBtn.addEventListener('click', mobileNavToogle);
    }

    // ✅ Clic sur les liens du menu
    const navmenuLinks = document.querySelectorAll('#navmenu a');
    navmenuLinks.forEach(navmenu => {
      navmenu.addEventListener('click', () => {
        if (document.querySelector('body').classList.contains('mobile-nav-active')) {
          mobileNavToogle();
        }
      });
    });

    // ✅ Scroll class toggle
    function toggleScrolled() {
      const body = document.querySelector('body');
      const header = document.querySelector('#header');
      if (!body || !header) return;

      if (
        header.classList.contains('scroll-up-sticky') ||
        header.classList.contains('sticky-top') ||
        header.classList.contains('fixed-top')
      ) {
        window.scrollY > 100
          ? body.classList.add('scrolled')
          : body.classList.remove('scrolled');
      }
    }

    window.addEventListener('scroll', toggleScrolled);
    window.addEventListener('load', toggleScrolled);

    // ✅ Scroll top button
    let scrollTop = document.querySelector('.scroll-top');
    if (scrollTop) {
      function toggleScrollTop() {
        window.scrollY > 100
          ? scrollTop.classList.add('active')
          : scrollTop.classList.remove('active');
      }
      scrollTop.addEventListener('click', (e) => {
        e.preventDefault();
        window.scrollTo({ top: 0, behavior: 'smooth' });
      });
      window.addEventListener('load', toggleScrollTop);
      document.addEventListener('scroll', toggleScrollTop);
    }

    // ✅ Animation on scroll
    function aosInit() {
      if (typeof AOS !== 'undefined') {
        AOS.init({
          duration: 600,
          easing: 'ease-in-out',
          once: true,
          mirror: false
        });
      }
    }
    window.addEventListener('load', aosInit);

    // ✅ GLightbox
    if (typeof GLightbox !== 'undefined') {
      GLightbox({ selector: '.glightbox' });
    }

    // ✅ PureCounter
    if (typeof PureCounter !== 'undefined') {
      new PureCounter();
    }

    // ✅ Swiper init
    function initSwiper() {
      if (typeof Swiper === 'undefined') return;
      document.querySelectorAll(".init-swiper").forEach(function (el) {
        const configEl = el.querySelector(".swiper-config");
        if (!configEl) return;
        try {
          const config = JSON.parse(configEl.innerHTML.trim());
          new Swiper(el, config);
        } catch (e) {
          console.error("Erreur Swiper :", e);
        }
      });
    }
    window.addEventListener("load", initSwiper);

    // ✅ Scroll vers hash
    window.addEventListener('load', function () {
      if (window.location.hash) {
        const section = document.querySelector(window.location.hash);
        if (section) {
          setTimeout(() => {
            let marginTop = getComputedStyle(section).scrollMarginTop;
            window.scrollTo({
              top: section.offsetTop - parseInt(marginTop),
              behavior: 'smooth'
            });
          }, 100);
        }
      }
    });

    // ✅ Scrollspy
    function navmenuScrollspy() {
      const navmenulinks = document.querySelectorAll('.navmenu a');
      navmenulinks.forEach(link => {
        if (!link.hash) return;
        const section = document.querySelector(link.hash);
        if (!section) return;
        const pos = window.scrollY + 200;
        if (pos >= section.offsetTop && pos <= section.offsetTop + section.offsetHeight) {
          document.querySelectorAll('.navmenu a.active').forEach(el => el.classList.remove('active'));
          link.classList.add('active');
        } else {
          link.classList.remove('active');
        }
      });
    }
    if (document.querySelector('.navmenu a')) {
      window.addEventListener('load', navmenuScrollspy);
      document.addEventListener('scroll', navmenuScrollspy);
    }

  });
})();

