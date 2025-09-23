const initSmoothScroll = () => {
  const header = document.querySelector('.site-header');
  const headerOffset = header ? header.offsetHeight : 0;
  const links = document.querySelectorAll('a[href^="#"]');

  links.forEach((link) => {
    link.addEventListener('click', (event) => {
      const href = link.getAttribute('href');
      if (!href || href === '#') {
        return;
      }

      const targetId = href.substring(1);
      const target = document.getElementById(targetId);

      if (target) {
        event.preventDefault();
        const elementPosition = target.getBoundingClientRect().top + window.pageYOffset;
        const offsetPosition = elementPosition - headerOffset + 1;

        window.scrollTo({
          top: offsetPosition,
          behavior: 'smooth',
        });

        if (history.replaceState) {
          history.replaceState(null, '', `#${targetId}`);
        } else {
          window.location.hash = targetId;
        }
      }
    });
  });
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initSmoothScroll);
} else {
  initSmoothScroll();
}
