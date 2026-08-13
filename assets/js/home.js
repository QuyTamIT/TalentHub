/**
 * TalentHub - Public Home Page Scripts
 * Modular JavaScript for header scrolling, responsive mobile menu, smooth scrolling, 
 * active section highlighting, and statistics counter animations.
 * 
 * Note for Junior Developers:
 * - Temporary routes (login.php & role-selection.php) are handled safely with fallbacks until backend modules are ready.
 * - Data configs (e.g. statistics) are stored in JS constants below.
 */

document.addEventListener('DOMContentLoaded', () => {
    initHeaderScroll();
    initMobileNav();
    initSmoothScroll();
    initActiveSectionObserver();
    initStatsCounter();
    initCtaHandlers();
});

/* ==========================================================================
   1. Sticky Header Scroll Effect
   ========================================================================== */
function initHeaderScroll() {
    const header = document.querySelector('.site-header');
    if (!header) return;

    window.addEventListener('scroll', () => {
        if (window.scrollY > 20) {
            header.classList.add('is-scrolled');
        } else {
            header.classList.remove('is-scrolled');
        }
    }, { passive: true });
}

/* ==========================================================================
   2. Mobile Navigation Menu Drawer & Body Scroll Lock
   ========================================================================== */
function initMobileNav() {
    const toggleBtn = document.querySelector('.site-header__mobile-toggle');
    const mobileMenu = document.querySelector('.mobile-menu');

    if (!toggleBtn || !mobileMenu) return;

    function openMenu() {
        toggleBtn.setAttribute('aria-expanded', 'true');
        mobileMenu.setAttribute('aria-hidden', 'false');
        mobileMenu.classList.add('is-active');
        document.body.classList.add('mobile-menu-open');
    }

    function closeMenu() {
        toggleBtn.setAttribute('aria-expanded', 'false');
        mobileMenu.setAttribute('aria-hidden', 'true');
        mobileMenu.classList.remove('is-active');
        document.body.classList.remove('mobile-menu-open');
    }

    toggleBtn.addEventListener('click', () => {
        const isOpen = mobileMenu.classList.contains('is-active');
        if (isOpen) {
            closeMenu();
        } else {
            openMenu();
        }
    });

    // Close mobile menu when clicking any mobile link or action button
    const mobileLinks = mobileMenu.querySelectorAll('.mobile-menu__link, .mobile-menu__btn');
    mobileLinks.forEach(link => {
        link.addEventListener('click', () => {
            closeMenu();
        });
    });

    // Close menu when pressing Escape key
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && mobileMenu.classList.contains('is-active')) {
            closeMenu();
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
                
                // Account for sticky header height
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
   4. Active Section Observer (Highlights current section link in header)
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
   5. Platform Statistics (Mock Data & Counter Animation)
   ========================================================================== */
const MOCK_STATISTICS = [
    { id: 'students', target: 50000, suffix: '+', label: 'Học sinh / Sinh viên' },
    { id: 'schools', target: 150, suffix: '+', label: 'Nhà trường đồng hành' },
    { id: 'activities', target: 500, suffix: '+', label: 'Hoạt động / Tháng' },
    { id: 'enterprises', target: 80, suffix: '+', label: 'Doanh nghiệp liên kết' }
];

function initStatsCounter() {
    const statsSection = document.querySelector('#statistics');
    if (!statsSection) return;

    let animated = false;

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
    const statElements = document.querySelectorAll('.stat-number[data-target]');

    statElements.forEach(el => {
        const target = parseInt(el.getAttribute('data-target'), 10);
        const suffix = el.getAttribute('data-suffix') || '';
        const duration = 1500;
        const frameRate = 1000 / 60;
        const totalFrames = Math.round(duration / frameRate);
        let frame = 0;

        const counter = setInterval(() => {
            frame++;
            const progress = frame / totalFrames;
            const currentNumber = Math.round(target * (1 - Math.pow(1 - progress, 2)));

            el.textContent = currentNumber.toLocaleString('vi-VN') + suffix;

            if (frame >= totalFrames) {
                el.textContent = target.toLocaleString('vi-VN') + suffix;
                clearInterval(counter);
            }
        }, frameRate);
    });
}

/* ==========================================================================
   6. CTA Buttons Action Handlers (Future Backend Routes Handlers)
   ========================================================================== */
function initCtaHandlers() {
    const loginButtons = document.querySelectorAll('[data-cta="login"]');
    const appButtons = document.querySelectorAll('[data-cta="app"]');

    loginButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            // TODO for future Backend Auth module:
            // When authentication API/pages are created, remove event.preventDefault() 
            // to allow navigation to login.php or open Auth Modal.
            const href = btn.getAttribute('href');
            if (href === 'login.php' || href.endsWith('login.php')) {
                // If login.php page is not created yet in current environment, scroll to CTA section gracefully
                const ctaSection = document.querySelector('#app');
                if (ctaSection && !document.querySelector('form#login-form')) {
                    e.preventDefault();
                    ctaSection.scrollIntoView({ behavior: 'smooth' });
                }
            }
        });
    });

    appButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            const href = btn.getAttribute('href');
            if (href === 'role-selection.php' || href.endsWith('role-selection.php')) {
                // Navigating to Role Selection page (role-selection.php)
                // Browser handles standard navigation natively.
                return;
            }
        });
    });
}
