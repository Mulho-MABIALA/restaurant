<!-- Section Hero Carrousel -->
<section id="hero" class="relative w-full h-screen min-h-[600px] overflow-hidden bg-black">
    <!-- Progress Bar -->
    <div id="progressBar" class="absolute bottom-0 left-0 h-1 bg-yellow-500 transition-all duration-[5000ms] linear z-40" style="width: 0%;"></div>

    <!-- Carousel Container -->
    <div id="carouselContainer" class="relative w-full h-full flex transition-transform duration-1000" style="transition-timing-function: cubic-bezier(0.23, 1, 0.32, 1);">

        <!-- Slide 1 -->
        <div class="carousel-slide min-w-full h-full relative flex items-center justify-center bg-cover bg-center bg-no-repeat bg-fixed" style="background-image: linear-gradient(135deg, rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.4)), url('assets/img/slider1.jpg');">
            <!-- Overlay -->
            <div class="absolute inset-0 z-20 bg-gradient-to-r from-black/70 via-black/40 to-black/60 pointer-events-none"></div>

            <!-- Floating Elements -->
            <div class="absolute inset-0 pointer-events-none z-10 overflow-hidden">
                <div class="floating-element absolute w-1.5 h-1.5 rounded-full bg-yellow-500/60" style="left: 5%; top: 20%; animation: float-carousel 8s infinite ease-in-out; animation-delay: 0s;"></div>
                <div class="floating-element absolute w-1.5 h-1.5 rounded-full bg-white/40" style="left: 15%; top: 60%; animation: float-carousel 12s infinite ease-in-out; animation-delay: 2s;"></div>
                <div class="floating-element diamond absolute w-2 h-2 bg-transparent border border-yellow-500/50" style="left: 25%; top: 30%; transform: rotate(45deg); animation: float-carousel 8s infinite ease-in-out; animation-delay: 4s;"></div>
                <div class="floating-element absolute w-1.5 h-1.5 rounded-full bg-white/40" style="left: 35%; top: 70%; animation: float-carousel 12s infinite ease-in-out; animation-delay: 1s;"></div>
                <div class="floating-element absolute w-1.5 h-1.5 rounded-full bg-yellow-500/60" style="left: 45%; top: 10%; animation: float-carousel 8s infinite ease-in-out; animation-delay: 3s;"></div>
                <div class="floating-element diamond absolute w-2 h-2 bg-transparent border border-yellow-500/50" style="left: 55%; top: 50%; transform: rotate(45deg); animation: float-carousel 12s infinite ease-in-out; animation-delay: 5s;"></div>
                <div class="floating-element absolute w-1.5 h-1.5 rounded-full bg-white/40" style="left: 65%; top: 80%; animation: float-carousel 12s infinite ease-in-out; animation-delay: 1.5s;"></div>
                <div class="floating-element absolute w-1.5 h-1.5 rounded-full bg-yellow-500/60" style="left: 75%; top: 25%; animation: float-carousel 8s infinite ease-in-out; animation-delay: 3.5s;"></div>
                <div class="floating-element diamond absolute w-2 h-2 bg-transparent border border-yellow-500/50" style="left: 85%; top: 65%; transform: rotate(45deg); animation: float-carousel 8s infinite ease-in-out; animation-delay: 0.5s;"></div>
                <div class="floating-element absolute w-1.5 h-1.5 rounded-full bg-white/40" style="left: 95%; top: 40%; animation: float-carousel 12s infinite ease-in-out; animation-delay: 2.5s;"></div>
            </div>

            <!-- Content -->
            <div class="slide-content relative z-30 text-center text-white max-w-3xl px-8 md:px-0">
                <div class="slide-pretitle text-sm md:text-base font-medium uppercase tracking-widest text-yellow-500 mb-4 opacity-90">Restaurant Mulho</div>
                <h1 class="slide-title text-4xl sm:text-5xl lg:text-7xl font-bold font-serif leading-tight mb-6 bg-gradient-to-r from-white via-gray-100 to-gray-300 bg-clip-text text-transparent">Saveurs Authentiques du Sénégal</h1>
                <p class="slide-subtitle text-lg sm:text-xl lg:text-2xl font-light leading-relaxed mb-8 text-white/90 max-w-2xl mx-auto">Découvrez une expérience culinaire exceptionnelle où tradition et modernité se rencontrent. Nos chefs passionnés préparent chaque plat avec des ingrédients frais et authentiques.</p>
                <div class="slide-cta-wrapper flex flex-col sm:flex-row gap-4 justify-center flex-wrap">
                    <a href="menu.php" class="inline-flex items-center px-8 py-4 bg-gradient-to-r from-yellow-500 to-yellow-400 hover:from-yellow-400 hover:to-yellow-300 text-black font-bold rounded-full uppercase tracking-wide transition-all duration-300 transform hover:scale-105 hover:-translate-y-1 shadow-lg hover:shadow-xl">Découvrir le Menu</a>
                    <a href="#about" class="inline-flex items-center px-8 py-4 bg-white/10 hover:bg-white/20 text-white font-bold rounded-full uppercase tracking-wide backdrop-blur-xl border border-white/20 transition-all duration-300 transform hover:scale-105 hover:-translate-y-1 shadow-lg">Notre Histoire</a>
                </div>
            </div>
        </div>

        <!-- Slide 2 -->
        <div class="carousel-slide min-w-full h-full relative flex items-center justify-center bg-cover bg-center bg-no-repeat bg-fixed" style="background-image: linear-gradient(135deg, rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.4)), url('assets/img/slider2.jpg');">
            <!-- Overlay -->
            <div class="absolute inset-0 z-20 bg-gradient-to-r from-black/70 via-black/40 to-black/60 pointer-events-none"></div>

            <!-- Floating Elements -->
            <div class="absolute inset-0 pointer-events-none z-10 overflow-hidden">
                <div class="floating-element absolute w-1.5 h-1.5 rounded-full bg-yellow-500/60" style="left: 10%; top: 15%; animation: float-carousel 8s infinite ease-in-out; animation-delay: 0.5s;"></div>
                <div class="floating-element diamond absolute w-2 h-2 bg-transparent border border-yellow-500/50" style="left: 20%; top: 55%; transform: rotate(45deg); animation: float-carousel 12s infinite ease-in-out; animation-delay: 2.5s;"></div>
                <div class="floating-element absolute w-1.5 h-1.5 rounded-full bg-white/40" style="left: 30%; top: 35%; animation: float-carousel 8s infinite ease-in-out; animation-delay: 4.5s;"></div>
                <div class="floating-element absolute w-1.5 h-1.5 rounded-full bg-white/40" style="left: 40%; top: 75%; animation: float-carousel 12s infinite ease-in-out; animation-delay: 1.5s;"></div>
                <div class="floating-element diamond absolute w-2 h-2 bg-transparent border border-yellow-500/50" style="left: 50%; top: 15%; transform: rotate(45deg); animation: float-carousel 8s infinite ease-in-out; animation-delay: 3.5s;"></div>
                <div class="floating-element absolute w-1.5 h-1.5 rounded-full bg-yellow-500/60" style="left: 60%; top: 55%; animation: float-carousel 12s infinite ease-in-out; animation-delay: 5.5s;"></div>
                <div class="floating-element absolute w-1.5 h-1.5 rounded-full bg-white/40" style="left: 70%; top: 85%; animation: float-carousel 8s infinite ease-in-out; animation-delay: 2s;"></div>
                <div class="floating-element diamond absolute w-2 h-2 bg-transparent border border-yellow-500/50" style="left: 80%; top: 30%; transform: rotate(45deg); animation: float-carousel 12s infinite ease-in-out; animation-delay: 4s;"></div>
                <div class="floating-element absolute w-1.5 h-1.5 rounded-full bg-yellow-500/60" style="left: 90%; top: 70%; animation: float-carousel 8s infinite ease-in-out; animation-delay: 1s;"></div>
            </div>

            <!-- Content -->
            <div class="slide-content relative z-30 text-center text-white max-w-3xl px-8 md:px-0">
                <div class="slide-pretitle text-sm md:text-base font-medium uppercase tracking-widest text-yellow-500 mb-4 opacity-90">Ambiance Unique</div>
                <h1 class="slide-title text-4xl sm:text-5xl lg:text-7xl font-bold font-serif leading-tight mb-6 bg-gradient-to-r from-white via-gray-100 to-gray-300 bg-clip-text text-transparent">Un Cadre Chaleureux & Authentique</h1>
                <p class="slide-subtitle text-lg sm:text-xl lg:text-2xl font-light leading-relaxed mb-8 text-white/90 max-w-2xl mx-auto">Plongez dans une atmosphère conviviale qui célèbre la richesse culturelle du Sénégal. Parfait pour vos repas en famille, entre amis ou vos occasions spéciales.</p>
                <div class="slide-cta-wrapper flex flex-col sm:flex-row gap-4 justify-center flex-wrap">
                    <a href="#book-a-table" class="inline-flex items-center px-8 py-4 bg-gradient-to-r from-yellow-500 to-yellow-400 hover:from-yellow-400 hover:to-yellow-300 text-black font-bold rounded-full uppercase tracking-wide transition-all duration-300 transform hover:scale-105 hover:-translate-y-1 shadow-lg hover:shadow-xl">Réserver une Table</a>
                    <a href="gallery_public.php" class="inline-flex items-center px-8 py-4 bg-white/10 hover:bg-white/20 text-white font-bold rounded-full uppercase tracking-wide backdrop-blur-xl border border-white/20 transition-all duration-300 transform hover:scale-105 hover:-translate-y-1 shadow-lg">Voir la Galerie</a>
                </div>
            </div>
        </div>

        <!-- Slide 3 -->
        <div class="carousel-slide min-w-full h-full relative flex items-center justify-center bg-cover bg-center bg-no-repeat bg-fixed" style="background-image: linear-gradient(135deg, rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.4)), url('assets/img/slider3.jpg');">
            <!-- Overlay -->
            <div class="absolute inset-0 z-20 bg-gradient-to-r from-black/70 via-black/40 to-black/60 pointer-events-none"></div>

            <!-- Floating Elements -->
            <div class="absolute inset-0 pointer-events-none z-10 overflow-hidden">
                <div class="floating-element diamond absolute w-2 h-2 bg-transparent border border-yellow-500/50" style="left: 8%; top: 25%; transform: rotate(45deg); animation: float-carousel 8s infinite ease-in-out; animation-delay: 1s;"></div>
                <div class="floating-element absolute w-1.5 h-1.5 rounded-full bg-white/40" style="left: 18%; top: 65%; animation: float-carousel 12s infinite ease-in-out; animation-delay: 3s;"></div>
                <div class="floating-element absolute w-1.5 h-1.5 rounded-full bg-yellow-500/60" style="left: 28%; top: 45%; animation: float-carousel 8s infinite ease-in-out; animation-delay: 5s;"></div>
                <div class="floating-element diamond absolute w-2 h-2 bg-transparent border border-yellow-500/50" style="left: 38%; top: 85%; transform: rotate(45deg); animation: float-carousel 12s infinite ease-in-out; animation-delay: 2s;"></div>
                <div class="floating-element absolute w-1.5 h-1.5 rounded-full bg-white/40" style="left: 48%; top: 25%; animation: float-carousel 8s infinite ease-in-out; animation-delay: 4s;"></div>
                <div class="floating-element absolute w-1.5 h-1.5 rounded-full bg-yellow-500/60" style="left: 58%; top: 65%; animation: float-carousel 12s infinite ease-in-out; animation-delay: 6s;"></div>
                <div class="floating-element diamond absolute w-2 h-2 bg-transparent border border-yellow-500/50" style="left: 68%; top: 95%; transform: rotate(45deg); animation: float-carousel 8s infinite ease-in-out; animation-delay: 2.5s;"></div>
                <div class="floating-element absolute w-1.5 h-1.5 rounded-full bg-white/40" style="left: 78%; top: 35%; animation: float-carousel 12s infinite ease-in-out; animation-delay: 4.5s;"></div>
                <div class="floating-element absolute w-1.5 h-1.5 rounded-full bg-yellow-500/60" style="left: 88%; top: 75%; animation: float-carousel 8s infinite ease-in-out; animation-delay: 1.5s;"></div>
            </div>

            <!-- Content -->
            <div class="slide-content relative z-30 text-center text-white max-w-3xl px-8 md:px-0">
                <div class="slide-pretitle text-sm md:text-base font-medium uppercase tracking-widest text-yellow-500 mb-4 opacity-90">Événements Privés</div>
                <h1 class="slide-title text-4xl sm:text-5xl lg:text-7xl font-bold font-serif leading-tight mb-6 bg-gradient-to-r from-white via-gray-100 to-gray-300 bg-clip-text text-transparent">Célébrez Vos Moments Précieux</h1>
                <p class="slide-subtitle text-lg sm:text-xl lg:text-2xl font-light leading-relaxed mb-8 text-white/90 max-w-2xl mx-auto">Organisez vos célébrations, événements d'entreprise et réceptions dans un cadre exceptionnel. Notre équipe personnalise chaque détail pour créer des souvenirs inoubliables.</p>
                <div class="slide-cta-wrapper flex flex-col sm:flex-row gap-4 justify-center flex-wrap">
                    <a href="#events" class="inline-flex items-center px-8 py-4 bg-gradient-to-r from-yellow-500 to-yellow-400 hover:from-yellow-400 hover:to-yellow-300 text-black font-bold rounded-full uppercase tracking-wide transition-all duration-300 transform hover:scale-105 hover:-translate-y-1 shadow-lg hover:shadow-xl">Organiser un Événement</a>
                    <a href="#contact" class="inline-flex items-center px-8 py-4 bg-white/10 hover:bg-white/20 text-white font-bold rounded-full uppercase tracking-wide backdrop-blur-xl border border-white/20 transition-all duration-300 transform hover:scale-105 hover:-translate-y-1 shadow-lg">Nous Contacter</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Dots -->
    <div id="carouselNav" class="absolute bottom-10 left-1/2 transform -translate-x-1/2 flex gap-3 z-40 bg-black/30 px-5 py-3 rounded-full backdrop-blur-xl border border-white/10">
        <div class="nav-dot w-2.5 h-2.5 rounded-full bg-white/40 cursor-pointer transition-all duration-300 active" data-slide="0"></div>
        <div class="nav-dot w-2.5 h-2.5 rounded-full bg-white/40 cursor-pointer transition-all duration-300" data-slide="1"></div>
        <div class="nav-dot w-2.5 h-2.5 rounded-full bg-white/40 cursor-pointer transition-all duration-300" data-slide="2"></div>
    </div>

    <!-- Navigation Arrows -->
    <button id="prevBtn" class="carousel-arrow prev absolute top-1/2 left-10 transform -translate-y-1/2 w-16 h-16 md:w-15 md:h-15 sm:w-12 sm:h-12 rounded-full bg-black/40 border border-white/20 text-white text-2xl flex items-center justify-center cursor-pointer transition-all duration-300 hover:bg-yellow-500/90 hover:border-yellow-500 hover:scale-110 hover:shadow-lg z-40 backdrop-blur-xl">‹</button>
    <button id="nextBtn" class="carousel-arrow next absolute top-1/2 right-10 transform -translate-y-1/2 w-16 h-16 md:w-15 md:h-15 sm:w-12 sm:h-12 rounded-full bg-black/40 border border-white/20 text-white text-2xl flex items-center justify-center cursor-pointer transition-all duration-300 hover:bg-yellow-500/90 hover:border-yellow-500 hover:scale-110 hover:shadow-lg z-40 backdrop-blur-xl">›</button>
</section>

<style>
    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(80px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes fadeInDown {
        from {
            opacity: 0;
            transform: translateY(-30px);
        }
        to {
            opacity: 0.9;
            transform: translateY(0);
        }
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes float-carousel {
        0%, 100% {
            transform: translateY(0px) translateX(0px) rotate(0deg);
            opacity: 0;
        }
        25% {
            transform: translateY(-80px) translateX(20px) rotate(90deg);
            opacity: 0.8;
        }
        50% {
            transform: translateY(-160px) translateX(-10px) rotate(180deg);
            opacity: 1;
        }
        75% {
            transform: translateY(-240px) translateX(30px) rotate(270deg);
            opacity: 0.6;
        }
    }

    .slide-pretitle {
        animation: fadeInDown 1s ease-out 0.3s both;
    }

    .slide-title {
        animation: fadeInUp 1s ease-out 0.5s both;
    }

    .slide-subtitle {
        animation: fadeInUp 1s ease-out 0.7s both;
    }

    .slide-cta-wrapper {
        animation: fadeInUp 1s ease-out 0.9s both;
    }

    .carousel-slide {
        will-change: transform;
    }

    #carouselContainer {
        will-change: transform;
    }

    .nav-dot.active {
        background: #eab308;
        transform: scale(1.4);
        box-shadow: 0 0 20px rgba(234, 179, 8, 0.6);
    }

    .nav-dot:hover:not(.active) {
        background: rgba(255, 255, 255, 0.7);
        transform: scale(1.2);
    }

    @media (max-width: 768px) {
        .carousel-slide {
            background-attachment: scroll;
        }

        .carousel-arrow {
            width: 45px !important;
            height: 45px !important;
            font-size: 1rem !important;
        }

        .carousel-arrow.prev {
            left: 20px !important;
        }

        .carousel-arrow.next {
            right: 20px !important;
        }

        #carouselNav {
            bottom: 30px !important;
            padding: 10px 16px !important;
        }
    }

    @media (max-width: 480px) {
        .carousel-arrow {
            width: 40px !important;
            height: 40px !important;
            font-size: 0.9rem !important;
        }

        .carousel-arrow.prev {
            left: 15px !important;
        }

        .carousel-arrow.next {
            right: 15px !important;
        }

        #carouselNav {
            bottom: 25px !important;
        }
    }
</style>

<script>
    class ProfessionalCarousel {
        constructor() {
            this.currentSlide = 0;
            this.totalSlides = 3;
            this.isAnimating = false;
            this.autoPlayInterval = null;
            this.progressInterval = null;
            this.autoPlayDuration = 6000;

            this.container = document.getElementById('carouselContainer');
            this.navDots = document.querySelectorAll('.nav-dot');
            this.prevBtn = document.getElementById('prevBtn');
            this.nextBtn = document.getElementById('nextBtn');
            this.progressBar = document.getElementById('progressBar');

            this.init();
        }

        init() {
            this.setupEventListeners();
            this.startAutoPlay();
            this.animateSlideContent();
            this.preloadImages();
        }

        preloadImages() {
            const slides = document.querySelectorAll('.carousel-slide');
            slides.forEach(slide => {
                const bgImage = slide.style.backgroundImage;
                if (bgImage && bgImage !== 'none') {
                    const imageUrl = bgImage.replace(/url\(['"]?(.*?)['"]?\)/, '$1');
                    const img = new Image();
                    img.src = imageUrl;
                }
            });
        }

        setupEventListeners() {
            this.navDots.forEach((dot, index) => {
                dot.addEventListener('click', () => this.goToSlide(index));
            });

            this.prevBtn.addEventListener('click', () => this.previousSlide());
            this.nextBtn.addEventListener('click', () => this.nextSlide());

            const carousel = this.container.parentElement;
            carousel.addEventListener('mouseenter', () => {
                this.stopAutoPlay();
                this.stopProgress();
            });

            carousel.addEventListener('mouseleave', () => {
                this.startAutoPlay();
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowLeft') this.previousSlide();
                if (e.key === 'ArrowRight') this.nextSlide();
                if (e.key === ' ') {
                    e.preventDefault();
                    this.toggleAutoPlay();
                }
            });

            this.setupTouchSupport();

            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    this.stopAutoPlay();
                    this.stopProgress();
                } else {
                    this.startAutoPlay();
                }
            });
        }

        setupTouchSupport() {
            let startX = null;
            let startY = null;
            let isDragging = false;

            this.container.addEventListener('touchstart', (e) => {
                startX = e.touches[0].clientX;
                startY = e.touches[0].clientY;
                isDragging = false;
            }, { passive: true });

            this.container.addEventListener('touchmove', (e) => {
                if (!startX || !startY) return;

                const deltaX = Math.abs(e.touches[0].clientX - startX);
                const deltaY = Math.abs(e.touches[0].clientY - startY);

                if (deltaX > deltaY && deltaX > 10) {
                    isDragging = true;
                    e.preventDefault();
                }
            }, { passive: false });

            this.container.addEventListener('touchend', (e) => {
                if (!startX || !isDragging) return;

                const endX = e.changedTouches[0].clientX;
                const diff = startX - endX;

                if (Math.abs(diff) > 50) {
                    if (diff > 0) {
                        this.nextSlide();
                    } else {
                        this.previousSlide();
                    }
                }

                startX = null;
                startY = null;
                isDragging = false;
            }, { passive: true });
        }

        goToSlide(slideIndex) {
            if (this.isAnimating || slideIndex === this.currentSlide) return;

            this.isAnimating = true;
            this.currentSlide = slideIndex;

            const translateX = -slideIndex * 100;
            this.container.style.transform = `translateX(${translateX}%)`;

            this.updateNavigation();
            this.animateSlideContent();
            this.resetProgress();

            setTimeout(() => {
                this.isAnimating = false;
            }, 1000);
        }

        nextSlide() {
            const nextIndex = (this.currentSlide + 1) % this.totalSlides;
            this.goToSlide(nextIndex);
        }

        previousSlide() {
            const prevIndex = this.currentSlide === 0 ? this.totalSlides - 1 : this.currentSlide - 1;
            this.goToSlide(prevIndex);
        }

        updateNavigation() {
            this.navDots.forEach((dot, index) => {
                dot.classList.toggle('active', index === this.currentSlide);
            });
        }

        animateSlideContent() {
            const slides = document.querySelectorAll('.carousel-slide');
            slides.forEach((slide, index) => {
                const content = slide.querySelector('.slide-content');
                const elements = content.querySelectorAll('.slide-pretitle, .slide-title, .slide-subtitle, .slide-cta-wrapper');

                if (index === this.currentSlide) {
                    elements.forEach((el, i) => {
                        el.style.animation = 'none';
                        setTimeout(() => {
                            const delay = i * 0.2;
                            if (el.classList.contains('slide-pretitle')) {
                                el.style.animation = `fadeInDown 1s ease-out ${delay}s both`;
                            } else {
                                el.style.animation = `fadeInUp 1s ease-out ${delay + 0.3}s both`;
                            }
                        }, 100);
                    });
                }
            });
        }

        startAutoPlay() {
            this.stopAutoPlay();
            this.autoPlayInterval = setInterval(() => {
                this.nextSlide();
            }, this.autoPlayDuration);
            this.startProgress();
        }

        stopAutoPlay() {
            if (this.autoPlayInterval) {
                clearInterval(this.autoPlayInterval);
                this.autoPlayInterval = null;
            }
        }

        toggleAutoPlay() {
            if (this.autoPlayInterval) {
                this.stopAutoPlay();
                this.stopProgress();
            } else {
                this.startAutoPlay();
            }
        }

        startProgress() {
            this.resetProgress();
            this.progressInterval = setInterval(() => {
                const currentWidth = parseFloat(this.progressBar.style.width) || 0;
                const increment = 100 / (this.autoPlayDuration / 100);
                const newWidth = currentWidth + increment;

                if (newWidth >= 100) {
                    this.resetProgress();
                } else {
                    this.progressBar.style.width = newWidth + '%';
                }
            }, 100);
        }

        stopProgress() {
            if (this.progressInterval) {
                clearInterval(this.progressInterval);
                this.progressInterval = null;
            }
        }

        resetProgress() {
            this.progressBar.style.width = '0%';
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        new ProfessionalCarousel();
    });

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.willChange = 'transform';
            } else {
                entry.target.style.willChange = 'auto';
            }
        });
    });

    document.querySelectorAll('.carousel-slide').forEach(slide => {
        observer.observe(slide);
    });
</script>
