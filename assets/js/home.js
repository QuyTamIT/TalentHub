/**
 * TalentHub - Public Home Page Scripts
 * Modular JavaScript for header scrolling, responsive mobile menu, smooth scrolling, 
 * active section highlighting, statistics counter animations, and interactive audience tabs.
 */

document.addEventListener('DOMContentLoaded', () => {
    initHeaderScroll();
    initMobileNav();
    initSmoothScroll();
    initActiveSectionObserver();
    initStatsCounter();
    initAudienceTabs();
    initCtaHandlers();
});

/* ==========================================================================
   1. Sticky Header Scroll Effect
   ========================================================================== */
function initHeaderScroll() {
    const header = document.querySelector('.site-header');
    if (!header) return;

    let ticking = false;
    const updateHeaderState = () => {
        header.classList.toggle('is-scrolled', window.scrollY > 20);
        ticking = false;
    };

    window.addEventListener('scroll', () => {
        if (ticking) return;
        ticking = true;
        window.requestAnimationFrame(updateHeaderState);
    }, { passive: true });

    updateHeaderState();
}

/* ==========================================================================
   2. Mobile Navigation Menu Drawer & Body Scroll Lock
   ========================================================================== */
function initMobileNav() {
    const toggleBtn = document.querySelector('.site-header__mobile-toggle');
    const mobileMenu = document.querySelector('.mobile-menu');

    if (!toggleBtn || !mobileMenu) return;

    function getFocusableElements() {
        return mobileMenu.querySelectorAll('a, button, input, select, textarea, [tabindex]:not([tabindex="-1"])');
    }

    function openMenu() {
        toggleBtn.setAttribute('aria-expanded', 'true');
        mobileMenu.setAttribute('aria-hidden', 'false');
        mobileMenu.classList.add('is-active');
        document.body.classList.add('mobile-menu-open');

        const firstFocusable = getFocusableElements()[0];
        if (firstFocusable) firstFocusable.focus();
    }

    function closeMenu() {
        toggleBtn.setAttribute('aria-expanded', 'false');
        mobileMenu.setAttribute('aria-hidden', 'true');
        mobileMenu.classList.remove('is-active');
        document.body.classList.remove('mobile-menu-open');
        toggleBtn.focus();
    }

    toggleBtn.addEventListener('click', () => {
        const isOpen = mobileMenu.classList.contains('is-active');
        if (isOpen) {
            closeMenu();
        } else {
            openMenu();
        }
    });

    const mobileLinks = mobileMenu.querySelectorAll('.mobile-menu__link, .mobile-menu__btn');
    mobileLinks.forEach(link => {
        link.addEventListener('click', () => {
            closeMenu();
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && mobileMenu.classList.contains('is-active')) {
            closeMenu();
            return;
        }

        /* Focus trap within mobile menu */
        if (event.key === 'Tab' && mobileMenu.classList.contains('is-active')) {
            const focusable = getFocusableElements();
            if (focusable.length === 0) return;

            const firstEl = focusable[0];
            const lastEl = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === firstEl) {
                event.preventDefault();
                lastEl.focus();
            } else if (!event.shiftKey && document.activeElement === lastEl) {
                event.preventDefault();
                firstEl.focus();
            }
        }
    });
}

/* ==========================================================================
   3. Smooth Scroll Navigation for Anchor Links
   ========================================================================== */
function initSmoothScroll() {
    const anchorLinks = document.querySelectorAll('a[href^="#"]');

    anchorLinks.forEach(link => {
        link.addEventListener('click', (event) => {
            const targetId = link.getAttribute('href');
            if (!targetId || targetId === '#') return;

            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                event.preventDefault();
                
                const headerHeight = document.querySelector('.site-header')?.offsetHeight || 72;
                const elementPosition = targetElement.getBoundingClientRect().top;
                const offsetPosition = elementPosition + window.pageYOffset - headerHeight;

                window.scrollTo({
                    top: offsetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });
}

/* ==========================================================================
   4. Active Section Observer
   ========================================================================== */
function initActiveSectionObserver() {
    const sections = document.querySelectorAll('section[id], header[id]');
    const navLinks = document.querySelectorAll('.site-nav__link, .mobile-menu__link');

    if (!sections.length || !navLinks.length) return;

    const observerOptions = {
        root: null,
        rootMargin: '-20% 0px -60% 0px',
        threshold: 0
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const currentId = `#${entry.target.getAttribute('id')}`;
                
                navLinks.forEach(link => {
                    if (link.getAttribute('href') === currentId) {
                        link.classList.add('site-nav__link--active', 'mobile-menu__link--active');
                    } else {
                        link.classList.remove('site-nav__link--active', 'mobile-menu__link--active');
                    }
                });
            }
        });
    }, observerOptions);

    sections.forEach(section => observer.observe(section));
}

/* ==========================================================================
   5. Platform Statistics Counter Animation
   ========================================================================== */
function initStatsCounter() {
    const statsSection = document.querySelector('#statistics');
    if (!statsSection) return;

    let animated = false;

    const showFinalValues = () => {
        document.querySelectorAll('.stat-number[data-target]').forEach(el => {
            const target = Number.parseInt(el.dataset.target, 10);
            const suffix = el.dataset.suffix || '';

            if (Number.isFinite(target)) {
                el.textContent = target.toLocaleString('vi-VN') + suffix;
            }
        });
    };

    // Avoid a first-paint jump for users who prefer less motion and for browsers
    // without IntersectionObserver support.
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) {
        showFinalValues();
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting && !animated) {
                animated = true;
                animateStatNumbers();
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.2 });

    observer.observe(statsSection);
}

function animateStatNumbers() {
    const duration = 1500;
    const startTime = performance.now();
    const targets = Array.from(document.querySelectorAll('.stat-number[data-target]')).map(el => ({
        el,
        target: Number.parseInt(el.dataset.target, 10),
        suffix: el.dataset.suffix || ''
    })).filter(item => Number.isFinite(item.target));

    if (!targets.length) return;

    const update = (now) => {
        const progress = Math.min((now - startTime) / duration, 1);
        const easedProgress = 1 - Math.pow(1 - progress, 2);

        targets.forEach(({ el, target, suffix }) => {
            const currentNumber = Math.round(target * easedProgress);
            el.textContent = currentNumber.toLocaleString('vi-VN') + suffix;
        });

        if (progress < 1) {
            window.requestAnimationFrame(update);
        } else {
            targets.forEach(({ el, target, suffix }) => {
                el.textContent = target.toLocaleString('vi-VN') + suffix;
            });
        }
    };

    window.requestAnimationFrame(update);
}

/* ==========================================================================
   6. Interactive Audience Role Tab Switcher
   ========================================================================== */
function initAudienceTabs() {
    const tabBtns = document.querySelectorAll('.audience-tab-btn');
    const tabPanels = document.querySelectorAll('.audience-panel');

    if (!tabBtns.length || !tabPanels.length) return;

    const activateTab = (btn, moveFocus = false) => {
        const targetId = btn.getAttribute('data-target');
        if (!targetId) return;

        tabBtns.forEach(item => {
            item.classList.remove('is-active');
            item.setAttribute('aria-selected', 'false');
            item.tabIndex = -1;
        });
        tabPanels.forEach(panel => {
            panel.classList.remove('is-active');
            panel.hidden = true;
        });

        btn.classList.add('is-active');
        btn.setAttribute('aria-selected', 'true');
        btn.tabIndex = 0;

        const targetPanel = document.getElementById(targetId);
        if (targetPanel) {
            targetPanel.classList.add('is-active');
            targetPanel.hidden = false;
        }
        if (moveFocus) btn.focus();
    };

    tabBtns.forEach((btn, index) => {
        btn.addEventListener('click', () => activateTab(btn));
        btn.addEventListener('keydown', event => {
            let nextIndex = null;
            if (event.key === 'ArrowRight' || event.key === 'ArrowDown') nextIndex = (index + 1) % tabBtns.length;
            if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') nextIndex = (index - 1 + tabBtns.length) % tabBtns.length;
            if (event.key === 'Home') nextIndex = 0;
            if (event.key === 'End') nextIndex = tabBtns.length - 1;
            if (nextIndex === null) return;
            event.preventDefault();
            activateTab(tabBtns[nextIndex], true);
        });
    });
}

/* ==========================================================================
   7. CTA Buttons Action Handlers
   ========================================================================== */
function initCtaHandlers() {
    const loginButtons = document.querySelectorAll('[data-cta="login"]');
    const appButtons = document.querySelectorAll('[data-cta="app"]');

    loginButtons.forEach(btn => btn.addEventListener('click', () => {
        document.body.classList.remove('mobile-menu-open');
    }));

    appButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            const href = btn.getAttribute('href');
            if (href === 'role-selection.php' || href.endsWith('role-selection.php')) {
                return;
            }
        });
    });
}
