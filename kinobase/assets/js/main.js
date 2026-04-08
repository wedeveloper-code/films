/**
 * KinoBase — Main JavaScript
 * No external dependencies. Vanilla JS only.
 */

/* ============================================================
   1. THEME MANAGEMENT
   ============================================================ */
(function () {
    var html = document.documentElement;
    var saved = localStorage.getItem('kinobase_theme');
    var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    var isDark = saved ? saved === 'dark' : prefersDark;
    if (!isDark) html.classList.remove('dark');
    else html.classList.add('dark');
})();

document.addEventListener('DOMContentLoaded', function () {

    /* ============================================================
       2. THEME TOGGLE
       ============================================================ */
    var themeBtn = document.getElementById('theme-toggle');
    var themeIcon = document.getElementById('theme-icon');

    function applyTheme(dark) {
        var html = document.documentElement;
        if (dark) {
            html.classList.add('dark');
            localStorage.setItem('kinobase_theme', 'dark');
            if (themeIcon) themeIcon.textContent = '🌙';
        } else {
            html.classList.remove('dark');
            localStorage.setItem('kinobase_theme', 'light');
            if (themeIcon) themeIcon.textContent = '☀️';
        }
    }

    // Set initial icon
    if (themeIcon) {
        themeIcon.textContent = document.documentElement.classList.contains('dark') ? '🌙' : '☀️';
    }

    if (themeBtn) {
        themeBtn.addEventListener('click', function () {
            applyTheme(!document.documentElement.classList.contains('dark'));
        });
    }

    /* ============================================================
       3. SEARCH OVERLAY
       ============================================================ */
    var searchToggle = document.getElementById('search-toggle');
    var searchOverlay = document.getElementById('search-overlay');
    var searchClose = document.getElementById('search-close');

    if (searchToggle && searchOverlay) {
        searchToggle.addEventListener('click', function () {
            searchOverlay.classList.add('active');
            var input = searchOverlay.querySelector('input');
            if (input) input.focus();
        });
    }
    if (searchClose && searchOverlay) {
        searchClose.addEventListener('click', function () {
            searchOverlay.classList.remove('active');
        });
    }
    if (searchOverlay) {
        searchOverlay.addEventListener('click', function (e) {
            if (e.target === searchOverlay) searchOverlay.classList.remove('active');
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') searchOverlay.classList.remove('active');
        });
    }

    /* ============================================================
       4. SETTINGS DROPDOWN (click-based for mobile)
       ============================================================ */
    var settingsMenu = document.getElementById('settings-menu');
    if (settingsMenu) {
        var settingsBtn = settingsMenu.querySelector('.icon-btn');
        settingsMenu.addEventListener('click', function (e) {
            e.stopPropagation();
            settingsMenu.classList.toggle('open');
        });
        document.addEventListener('click', function () {
            settingsMenu.classList.remove('open');
        });
    }

    /* ============================================================
       5. COOKIE HELPERS
       ============================================================ */
    function setCookie(name, value, days) {
        var expires = '';
        if (days) {
            var d = new Date();
            d.setTime(d.getTime() + days * 86400000);
            expires = '; expires=' + d.toUTCString();
        }
        document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/; SameSite=Lax';
    }

    function getCookie(name) {
        var prefix = name + '=';
        var parts = document.cookie.split(';');
        for (var i = 0; i < parts.length; i++) {
            var c = parts[i].trim();
            if (c.indexOf(prefix) === 0) {
                return decodeURIComponent(c.substring(prefix.length));
            }
        }
        return null;
    }

    /* ============================================================
       6. FAVORITES (cookie-based, 7 days)
       ============================================================ */
    var favorites = [];
    try {
        var raw = getCookie('kb_favorites');
        if (raw) favorites = JSON.parse(raw);
    } catch (e) {
        favorites = [];
    }

    // Initialize favorite button states on page load
    document.querySelectorAll('.fav-btn').forEach(function (btn) {
        var id = parseInt(btn.dataset.id, 10);
        if (favorites.indexOf(id) > -1) btn.classList.add('active');
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.fav-btn');
        if (!btn) return;
        var id = parseInt(btn.dataset.id, 10);
        var idx = favorites.indexOf(id);
        if (idx > -1) {
            favorites.splice(idx, 1);
            btn.classList.remove('active');
        } else {
            favorites.push(id);
            btn.classList.add('active');
        }
        setCookie('kb_favorites', JSON.stringify(favorites), 7);
    });

    /* ============================================================
       7. IMAGE GALLERY (hover zones)
       ============================================================ */
    function showGalleryImage(container, index) {
        var imgs = container.querySelectorAll('.gallery-img');
        var fills = container.querySelectorAll('.indicator-fill');
        imgs.forEach(function (img) { img.classList.remove('active'); });
        fills.forEach(function (f) { f.style.width = '0'; });
        if (imgs[index]) imgs[index].classList.add('active');
        if (fills[index]) fills[index].style.width = '100%';
    }

    function resetGallery(container) {
        showGalleryImage(container, 0);
    }

    document.querySelectorAll('.movie-gallery').forEach(function (gallery) {
        var zones = gallery.querySelectorAll('.gallery-zone');
        zones.forEach(function (zone, index) {
            zone.addEventListener('mouseenter', function () {
                showGalleryImage(gallery, index);
            });
        });
        gallery.addEventListener('mouseleave', function () {
            resetGallery(gallery);
        });
    });

    /* ============================================================
       8. PRICE TABS
       ============================================================ */
    document.addEventListener('click', function (e) {
        var tab = e.target.closest('.price-tab');
        if (!tab) return;

        var card = tab.closest('.movie-card, .single-pricing');
        if (!card) return;

        var type = tab.dataset.tab;
        card.querySelectorAll('.price-tab').forEach(function (t) { t.classList.remove('active'); });
        card.querySelectorAll('.price-grid').forEach(function (g) { g.classList.add('hidden'); });

        tab.classList.add('active');
        var grid = card.querySelector('.price-grid[data-type="' + type + '"]');
        if (grid) grid.classList.remove('hidden');
    });

    /* ============================================================
       9. COUPON (reveal + copy)
       ============================================================ */
    document.addEventListener('click', function (e) {
        // Reveal button click
        var revealBtn = e.target.closest('.coupon-btn');
        if (revealBtn) {
            var wrap = revealBtn.closest('.coupon-wrap');
            if (!wrap) return;
            var code = revealBtn.dataset.coupon || '';
            var codeEl = document.createElement('button');
            codeEl.className = 'coupon-code';
            codeEl.dataset.coupon = code;
            codeEl.textContent = code;
            codeEl.title = 'Нажмите, чтобы скопировать';
            wrap.innerHTML = '';
            wrap.appendChild(codeEl);
            return;
        }

        // Copy click
        var codeEl = e.target.closest('.coupon-code');
        if (codeEl) {
            var text = codeEl.dataset.coupon || codeEl.textContent;
            var original = codeEl.textContent;
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).catch(function () {});
            } else {
                var ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
            }
            codeEl.textContent = 'СКОПИРОВАНО!';
            setTimeout(function () { codeEl.textContent = original; }, 1500);
        }
    });

    /* ============================================================
       10. VIEW COUNTER (Intersection Observer + AJAX)
       ============================================================ */
    if (typeof KinoBase === 'undefined') return;

    var counted = new Set();

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (!entry.isIntersecting) return;
            var card = entry.target;
            var postId = parseInt(card.dataset.postId, 10);
            if (!postId || counted.has(postId)) return;
            counted.add(postId);
            observer.unobserve(card);

            var fd = new FormData();
            fd.append('action', 'kinobase_increment_views');
            fd.append('post_id', postId);
            fd.append('nonce', KinoBase.nonce);

            fetch(KinoBase.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success && data.data.views) {
                        var viewsEl = card.querySelector('.views-count');
                        if (viewsEl) viewsEl.textContent = data.data.views;
                    }
                })
                .catch(function () {});
        });
    }, { rootMargin: '0px 0px -50px 0px', threshold: 0.1 });

    document.querySelectorAll('.movie-card[data-post-id]').forEach(function (card) {
        observer.observe(card);
    });

    /* ============================================================
       11. CONTACT FORM (AJAX submit)
       ============================================================ */
    var contactForm = document.getElementById('contact-form');
    if (contactForm && typeof KinoBase !== 'undefined') {
        var submitBtn   = document.getElementById('contact-submit');
        var alertBox    = document.getElementById('contact-alert');

        function setAlert(msg, type) {
            alertBox.textContent = msg;
            alertBox.className = 'contact-alert ' + type;
        }

        contactForm.addEventListener('submit', function (e) {
            e.preventDefault();

            var name    = contactForm.querySelector('[name="contact_name"]').value.trim();
            var email   = contactForm.querySelector('[name="contact_email"]').value.trim();
            var message = contactForm.querySelector('[name="contact_message"]').value.trim();

            if (!name || !email || !message) {
                setAlert('Пожалуйста, заполните все поля.', 'error');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.classList.add('loading');
            alertBox.className = 'contact-alert';

            var fd = new FormData();
            fd.append('action',           'kinobase_contact');
            fd.append('nonce',            KinoBase.contactNonce);
            fd.append('contact_name',     name);
            fd.append('contact_email',    email);
            fd.append('contact_message',  message);

            fetch(KinoBase.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        setAlert(data.data.message, 'success');
                        contactForm.reset();
                    } else {
                        setAlert(data.data.message || 'Ошибка. Попробуйте позже.', 'error');
                    }
                })
                .catch(function () {
                    setAlert('Ошибка соединения. Попробуйте позже.', 'error');
                })
                .finally(function () {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('loading');
                });
        });
    }

}); // end DOMContentLoaded
