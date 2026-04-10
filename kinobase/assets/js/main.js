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
       4. SETTINGS DROPDOWN (click-based, gap prevents premature close)
       ============================================================ */
    var settingsMenu = document.getElementById('settings-menu');
    if (settingsMenu) {
        settingsMenu.addEventListener('click', function (e) {
            e.stopPropagation();
            settingsMenu.classList.toggle('open');
        });
        document.addEventListener('click', function () {
            settingsMenu.classList.remove('open');
        });
        // Keep open while mouse is over dropdown
        var settingsDrop = settingsMenu.querySelector('.settings-dropdown');
        if (settingsDrop) {
            settingsDrop.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        }
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
       7. IMAGE GALLERY — hover zones + auto-slideshow
       One card slides at a time: the hovered card on desktop,
       the card most visible on screen on mobile/touch.
       ============================================================ */
    function showGalleryImage(container, index) {
        var imgs  = container.querySelectorAll('.gallery-img');
        var fills = container.querySelectorAll('.indicator-fill');
        imgs.forEach(function (img, i)  { img.classList.toggle('active', i === index); });
        fills.forEach(function (f, i)   { f.style.width = i === index ? '100%' : '0'; });
        container._kbIdx = index; // track current index for slideshow
    }

    function resetGallery(container) { showGalleryImage(container, 0); }

    // Init galleries: set first fill to 100% so indicator state matches the active image
    document.querySelectorAll('.movie-gallery').forEach(function (gallery) {
        gallery._kbIdx = 0;
        var fills = gallery.querySelectorAll('.indicator-fill');
        if (fills[0]) fills[0].style.width = '100%';

        // Hover zones — manual image selection while hovering
        gallery.querySelectorAll('.gallery-zone').forEach(function (zone, i) {
            zone.addEventListener('mouseenter', function () { showGalleryImage(gallery, i); });
        });
    });

    // Auto-slideshow helpers — only ONE card active at a time
    var activeCardGallery = null;
    var cardSlideTimer    = null;

    function stopCardSlideshow() {
        clearInterval(cardSlideTimer);
        cardSlideTimer = null;
        if (activeCardGallery) {
            resetGallery(activeCardGallery);
            activeCardGallery = null;
        }
    }

    function startCardSlideshow(gallery) {
        var imgs = gallery.querySelectorAll('.gallery-img');
        if (imgs.length < 2) return;
        if (activeCardGallery === gallery) return; // already running
        stopCardSlideshow();
        activeCardGallery = gallery;
        showGalleryImage(gallery, (gallery._kbIdx + 1) % imgs.length); // immediate first switch
        cardSlideTimer = setInterval(function () {
            showGalleryImage(gallery, (gallery._kbIdx + 1) % imgs.length);
        }, 2500);
    }

    // Detect primary input type: touch vs pointer
    var isTouchPrimary = window.matchMedia('(hover: none) and (pointer: coarse)').matches;

    if (!isTouchPrimary) {
        // Desktop: slideshow starts when card is hovered, stops on leave
        document.querySelectorAll('.movie-card').forEach(function (card) {
            var gallery = card.querySelector('.movie-gallery');
            if (!gallery || gallery.querySelectorAll('.gallery-img').length < 2) return;
            card.addEventListener('mouseenter', function () { startCardSlideshow(gallery); });
            card.addEventListener('mouseleave', function () { stopCardSlideshow(); });
        });
    } else {
        // Mobile/touch: slideshow for the card currently on screen (≥60% visible)
        var cardSlideObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                var gallery = entry.target.querySelector('.movie-gallery');
                if (!gallery) return;
                if (entry.isIntersecting) {
                    startCardSlideshow(gallery);
                } else if (activeCardGallery === gallery) {
                    stopCardSlideshow();
                }
            });
        }, { threshold: 0.6 });

        document.querySelectorAll('.movie-card').forEach(function (card) {
            var gallery = card.querySelector('.movie-gallery');
            if (gallery && gallery.querySelectorAll('.gallery-img').length >= 2) {
                cardSlideObserver.observe(card);
            }
        });
    }

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

    /* ============================================================
       12. SINGLE MOVIE — POSTER NAVIGATION + AUTO-SLIDESHOW
       ============================================================ */
    var posterViewer = document.getElementById('single-poster-viewer');
    if (posterViewer) {
        var posterImgs   = posterViewer.querySelectorAll('.single-poster-img');
        var indicators   = posterViewer.querySelectorAll('.single-indicator');
        var thumbsWrap   = document.getElementById('single-thumbs');
        var thumbBtns    = thumbsWrap ? thumbsWrap.querySelectorAll('.single-thumb') : [];
        var prevBtn      = document.getElementById('poster-prev');
        var nextBtn      = document.getElementById('poster-next');
        var currentIdx   = 0;
        var total        = posterImgs.length;
        var slideshowTimer = null;

        function goToSlide(idx, userAction) {
            if (total === 0) return;
            idx = ((idx % total) + total) % total;
            posterImgs.forEach(function (img, i) { img.classList.toggle('active', i === idx); });
            indicators.forEach(function (dot, i) { dot.classList.toggle('active', i === idx); });
            thumbBtns.forEach(function (btn, i) { btn.classList.toggle('active', i === idx); });
            currentIdx = idx;
            if (userAction) stopSlideshow();
        }

        function stopSlideshow() {
            clearInterval(slideshowTimer);
            slideshowTimer = null;
        }

        // Auto-slideshow every 2.5 seconds (stops on any user interaction)
        if (total > 1) {
            slideshowTimer = setInterval(function () {
                goToSlide(currentIdx + 1, false);
            }, 2500);
        }

        if (prevBtn) prevBtn.addEventListener('click', function () { goToSlide(currentIdx - 1, true); });
        if (nextBtn) nextBtn.addEventListener('click', function () { goToSlide(currentIdx + 1, true); });

        indicators.forEach(function (dot) {
            dot.addEventListener('click', function () { goToSlide(parseInt(dot.dataset.index, 10), true); });
        });
        thumbBtns.forEach(function (btn) {
            btn.addEventListener('click', function () { goToSlide(parseInt(btn.dataset.index, 10), true); });
        });

        // Touch swipe on poster
        var swipeStartX = 0;
        posterViewer.addEventListener('touchstart', function (e) {
            swipeStartX = e.changedTouches[0].clientX;
        }, { passive: true });
        posterViewer.addEventListener('touchend', function (e) {
            var dx = e.changedTouches[0].clientX - swipeStartX;
            if (Math.abs(dx) > 40) goToSlide(dx < 0 ? currentIdx + 1 : currentIdx - 1, true);
        }, { passive: true });

        /* ============================================================
           LIGHTBOX — click poster image to open full-screen gallery
           ============================================================ */
        var lightbox    = document.getElementById('kb-lightbox');
        var lbMainImg   = document.getElementById('kb-lb-img');
        var lbThumbWrap = document.getElementById('kb-lb-thumbs');
        var lbCounter   = document.getElementById('kb-lb-counter');
        var lbCurrent   = 0;
        var lbImages    = [];

        if (lightbox && lbMainImg) {
            // Collect full-size URLs from data-full attribute
            posterImgs.forEach(function (img) {
                lbImages.push({
                    full:  img.dataset.full || img.src,
                    thumb: img.src,
                    alt:   img.alt,
                });
            });

            // Build lightbox thumbnail strip
            if (lbThumbWrap) {
                lbImages.forEach(function (item, idx) {
                    var btn = document.createElement('button');
                    btn.className = 'kb-lb-thumb' + (idx === 0 ? ' active' : '');
                    btn.setAttribute('aria-label', 'Фото ' + (idx + 1));
                    var tImg = document.createElement('img');
                    tImg.src = item.thumb;
                    tImg.alt = '';
                    tImg.loading = 'lazy';
                    btn.appendChild(tImg);
                    btn.addEventListener('click', function () { lbShow(idx); });
                    lbThumbWrap.appendChild(btn);
                });
            }

            function lbShow(idx) {
                lbCurrent = ((idx % lbImages.length) + lbImages.length) % lbImages.length;
                lbMainImg.src = lbImages[lbCurrent].full;
                lbMainImg.alt = lbImages[lbCurrent].alt;
                if (lbCounter) lbCounter.textContent = (lbCurrent + 1) + ' / ' + lbImages.length;
                if (lbThumbWrap) {
                    lbThumbWrap.querySelectorAll('.kb-lb-thumb').forEach(function (btn, i) {
                        btn.classList.toggle('active', i === lbCurrent);
                    });
                    // Scroll active thumb into view
                    var activeThumb = lbThumbWrap.querySelector('.kb-lb-thumb.active');
                    if (activeThumb) activeThumb.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
                }
            }

            function lbOpen(startIdx) {
                lbShow(startIdx);
                lightbox.classList.add('open');
                document.body.style.overflow = 'hidden';
                stopSlideshow();
            }

            function lbClose() {
                lightbox.classList.remove('open');
                document.body.style.overflow = '';
            }

            // Open on click of poster images
            posterImgs.forEach(function (img, idx) {
                img.style.cursor = 'zoom-in';
                img.addEventListener('click', function () { lbOpen(idx); });
            });

            // Close button
            var lbCloseBtn = document.getElementById('kb-lb-close');
            if (lbCloseBtn) lbCloseBtn.addEventListener('click', lbClose);

            // Click backdrop to close
            lightbox.addEventListener('click', function (e) {
                if (e.target === lightbox || e.target.classList.contains('kb-lb-inner')) lbClose();
            });

            // Prev / next buttons
            var lbPrev = document.getElementById('kb-lb-prev');
            var lbNext = document.getElementById('kb-lb-next');
            if (lbPrev) lbPrev.addEventListener('click', function () { lbShow(lbCurrent - 1); });
            if (lbNext) lbNext.addEventListener('click', function () { lbShow(lbCurrent + 1); });

            // Keyboard
            document.addEventListener('keydown', function (e) {
                if (!lightbox.classList.contains('open')) return;
                if (e.key === 'Escape')      lbClose();
                if (e.key === 'ArrowLeft')   lbShow(lbCurrent - 1);
                if (e.key === 'ArrowRight')  lbShow(lbCurrent + 1);
            });

            // Swipe in lightbox
            var lbTouchX = 0;
            lightbox.addEventListener('touchstart', function (e) {
                lbTouchX = e.changedTouches[0].clientX;
            }, { passive: true });
            lightbox.addEventListener('touchend', function (e) {
                var diff = lbTouchX - e.changedTouches[0].clientX;
                if (Math.abs(diff) > 50) lbShow(diff > 0 ? lbCurrent + 1 : lbCurrent - 1);
            }, { passive: true });
        }
    }

    /* ============================================================
       13. REVIEW FORM (AJAX submit)
       ============================================================ */
    var reviewForm = document.getElementById('review-form');
    if (reviewForm && typeof KinoBase !== 'undefined') {
        var reviewSubmit  = document.getElementById('review-submit');
        var reviewAlert   = document.getElementById('review-alert');
        var reviewList    = document.getElementById('review-list');

        function setReviewAlert(msg, type) {
            reviewAlert.textContent = msg;
            reviewAlert.className = 'review-alert ' + type;
        }

        reviewForm.addEventListener('submit', function (e) {
            e.preventDefault();

            var author  = reviewForm.querySelector('[name="review_author"]').value.trim();
            var text    = reviewForm.querySelector('[name="review_text"]').value.trim();
            var rating  = reviewForm.querySelector('[name="review_rating"]').value;
            var captcha = reviewForm.querySelector('[name="captcha_answer"]').value.trim();

            if (!author || !text || !captcha) {
                setReviewAlert('Пожалуйста, заполните все поля.', 'error');
                return;
            }

            reviewSubmit.disabled = true;
            reviewSubmit.classList.add('loading');
            reviewAlert.className = 'review-alert';

            var fd = new FormData(reviewForm);
            fd.append('action', 'kinobase_review');

            fetch(KinoBase.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        setReviewAlert(data.data.message, 'success');
                        reviewForm.reset();
                        if (data.data.html) {
                            if (!reviewList) {
                                reviewList = document.createElement('div');
                                reviewList.className = 'review-list';
                                reviewList.id = 'review-list';
                                reviewForm.closest('.single-reviews').appendChild(reviewList);
                            }
                            var tmp = document.createElement('div');
                            tmp.innerHTML = data.data.html;
                            reviewList.insertBefore(tmp.firstChild, reviewList.firstChild);
                        }
                    } else {
                        setReviewAlert(data.data.message || 'Ошибка. Попробуйте позже.', 'error');
                    }
                })
                .catch(function () {
                    setReviewAlert('Ошибка соединения. Попробуйте позже.', 'error');
                })
                .finally(function () {
                    reviewSubmit.disabled = false;
                    reviewSubmit.classList.remove('loading');
                });
        });
    }

    /* ============================================================
       14. BURGER MENU
       ============================================================ */
    var burgerBtn  = document.getElementById('burger-btn');
    var mobileNav  = document.getElementById('mobile-nav');

    if (burgerBtn && mobileNav) {
        burgerBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = mobileNav.classList.toggle('open');
            burgerBtn.classList.toggle('open', open);
            burgerBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            document.body.style.overflow = open ? 'hidden' : '';
        });

        // Close on outside click
        document.addEventListener('click', function (e) {
            if (mobileNav.classList.contains('open') && !mobileNav.contains(e.target) && e.target !== burgerBtn) {
                mobileNav.classList.remove('open');
                burgerBtn.classList.remove('open');
                burgerBtn.setAttribute('aria-expanded', 'false');
                document.body.style.overflow = '';
            }
        });

        // Close on link click
        mobileNav.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                mobileNav.classList.remove('open');
                burgerBtn.classList.remove('open');
                document.body.style.overflow = '';
            });
        });
    }

    /* ============================================================
       15. GALLERY CLICK → NAVIGATE TO POST
       ============================================================ */
    document.addEventListener('click', function (e) {
        var gallery = e.target.closest('.movie-gallery');
        if (!gallery || !gallery.dataset.href) return;
        // Don't navigate when clicking interactive elements on the poster
        if (e.target.closest('.fav-btn, .poster-actions, .poster-badges, .views-badge')) return;
        window.location.href = gallery.dataset.href;
    });

    /* ============================================================
       16. MOBILE GALLERY SWIPE (on movie cards)
       ============================================================ */
    document.querySelectorAll('.movie-gallery').forEach(function (gallery) {
        var swipeX = 0;
        gallery.addEventListener('touchstart', function (e) {
            swipeX = e.changedTouches[0].clientX;
        }, { passive: true });
        gallery.addEventListener('touchend', function (e) {
            var dx = e.changedTouches[0].clientX - swipeX;
            if (Math.abs(dx) < 30) return;
            var imgs = gallery.querySelectorAll('.gallery-img');
            var cur  = gallery._kbIdx || 0;
            var next = dx < 0 ? Math.min(cur + 1, imgs.length - 1) : Math.max(cur - 1, 0);
            showGalleryImage(gallery, next); // keeps _kbIdx in sync with slideshow
        }, { passive: true });
    });

    /* ============================================================
       17. MOBILE SLIDER — Prev/Next + dots for .movie-grid
       ============================================================ */
    function initMobileSliders() {
        if (window.innerWidth >= 640) return;

        document.querySelectorAll('.category-section').forEach(function (section) {
            var grid = section.querySelector('.movie-grid');
            if (!grid || grid.dataset.sliderInit) return;
            grid.dataset.sliderInit = '1';

            var cards = grid.querySelectorAll('.movie-card');
            if (cards.length < 2) return;

            // Build nav bar
            var nav = document.createElement('div');
            nav.className = 'slider-nav';

            var prevBtn = document.createElement('button');
            prevBtn.className = 'slider-btn';
            prevBtn.innerHTML = '&#8249;';
            prevBtn.setAttribute('aria-label', 'Предыдущий');

            var nextBtn = document.createElement('button');
            nextBtn.className = 'slider-btn';
            nextBtn.innerHTML = '&#8250;';
            nextBtn.setAttribute('aria-label', 'Следующий');

            var dotsWrap = document.createElement('div');
            dotsWrap.className = 'slider-dots';

            cards.forEach(function (_, i) {
                var dot = document.createElement('button');
                dot.className = 'slider-dot' + (i === 0 ? ' active' : '');
                dot.setAttribute('aria-label', 'Карточка ' + (i + 1));
                dot.dataset.index = String(i);
                dotsWrap.appendChild(dot);
            });

            nav.appendChild(prevBtn);
            nav.appendChild(dotsWrap);
            nav.appendChild(nextBtn);
            section.appendChild(nav);

            function getCardWidth() {
                return cards[0].offsetWidth + parseFloat(getComputedStyle(grid).gap || '14');
            }

            function getCurrentIndex() {
                return Math.round(grid.scrollLeft / getCardWidth());
            }

            function goTo(idx) {
                idx = Math.max(0, Math.min(idx, cards.length - 1));
                grid.scrollTo({ left: idx * getCardWidth(), behavior: 'smooth' });
            }

            function updateState() {
                var idx = getCurrentIndex();
                dotsWrap.querySelectorAll('.slider-dot').forEach(function (d, i) {
                    d.classList.toggle('active', i === idx);
                });
                prevBtn.disabled = idx === 0;
                nextBtn.disabled = idx === cards.length - 1;
            }

            prevBtn.addEventListener('click', function () { goTo(getCurrentIndex() - 1); });
            nextBtn.addEventListener('click', function () { goTo(getCurrentIndex() + 1); });
            dotsWrap.addEventListener('click', function (e) {
                var dot = e.target.closest('.slider-dot');
                if (dot) goTo(parseInt(dot.dataset.index, 10));
            });
            grid.addEventListener('scroll', updateState, { passive: true });

            updateState();
        });
    }

    initMobileSliders();

    // Re-init on resize crossing the 640px breakpoint
    var _prevMobile = window.innerWidth < 640;
    window.addEventListener('resize', function () {
        var isMobile = window.innerWidth < 640;
        if (isMobile && !_prevMobile) initMobileSliders();
        _prevMobile = isMobile;
    });

    /* ============================================================
       18. SWIPE HINT (shown once per device via localStorage)
       ============================================================ */
    if (window.innerWidth < 640 && !localStorage.getItem('kb_swipe_hint')) {
        var firstSection = document.querySelector('.category-section');
        if (firstSection) {
            var hint = document.createElement('div');
            hint.className = 'swipe-hint';
            hint.innerHTML = '<div class="swipe-hint-inner">← Листайте карточки →</div>';
            firstSection.style.position = 'relative';
            firstSection.appendChild(hint);
            localStorage.setItem('kb_swipe_hint', '1');
            setTimeout(function () { hint.remove(); }, 2600);
        }
    }

    /* ============================================================
       19. FILTER PANEL (sliding two-screen filter on archive pages)
       ============================================================ */
    var filterBtn   = document.getElementById('js-filter-btn');
    var filterPanel = document.getElementById('js-filter-panel');

    if (filterBtn && filterPanel) {
        filterBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var isOpen = filterPanel.classList.toggle('open');
            filterBtn.classList.toggle('active', isOpen);
            filterBtn.setAttribute('aria-expanded', String(isOpen));
            if (isOpen) showFilterScreen('filter-screen-main');
        });

        document.addEventListener('click', function (e) {
            if (filterPanel.classList.contains('open') &&
                !filterPanel.contains(e.target) &&
                !filterBtn.contains(e.target)) {
                closeFilterPanel();
            }
        });

        filterPanel.querySelectorAll('.filter-close-btn').forEach(function (btn) {
            btn.addEventListener('click', closeFilterPanel);
        });

        filterPanel.querySelectorAll('.filter-cat-btn').forEach(function (btn) {
            btn.addEventListener('click', function () { showFilterScreen(this.dataset.target); });
        });

        filterPanel.querySelectorAll('.filter-back-btn').forEach(function (btn) {
            btn.addEventListener('click', function () { showFilterScreen(this.dataset.target); });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeFilterPanel();
        });

        function showFilterScreen(id) {
            filterPanel.querySelectorAll('.filter-screen').forEach(function (s) {
                s.classList.remove('active');
            });
            var t = document.getElementById(id);
            if (t) t.classList.add('active');
        }

        function closeFilterPanel() {
            filterPanel.classList.remove('open');
            filterBtn.classList.remove('active');
            filterBtn.setAttribute('aria-expanded', 'false');
        }
    }

    /* ============================================================
       20. DESKTOP FILTER BAR DROPDOWNS (data-fb-toggle)
       ============================================================ */
    var fbBtns = document.querySelectorAll('[data-fb-toggle]');

    if (fbBtns.length) {
        fbBtns.forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var targetId = btn.getAttribute('data-fb-toggle');
                var drop = document.getElementById(targetId);
                if (!drop) return;

                var isOpen = drop.classList.contains('open');

                // Close all other drops
                document.querySelectorAll('.filter-bar-drop.open').forEach(function (d) {
                    d.classList.remove('open');
                });
                document.querySelectorAll('[data-fb-toggle]').forEach(function (b) {
                    b.setAttribute('aria-expanded', 'false');
                });

                // Toggle target
                if (!isOpen) {
                    drop.classList.add('open');
                    btn.setAttribute('aria-expanded', 'true');
                }
            });
        });

        // Close on outside click
        document.addEventListener('click', function () {
            document.querySelectorAll('.filter-bar-drop.open').forEach(function (d) {
                d.classList.remove('open');
            });
            document.querySelectorAll('[data-fb-toggle]').forEach(function (b) {
                b.setAttribute('aria-expanded', 'false');
            });
        });

        // Close on Escape
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.filter-bar-drop.open').forEach(function (d) {
                    d.classList.remove('open');
                });
                document.querySelectorAll('[data-fb-toggle]').forEach(function (b) {
                    b.setAttribute('aria-expanded', 'false');
                });
            }
        });
    }

}); // end DOMContentLoaded
