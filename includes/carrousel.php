
<style>
    /* === STYLES SPÉCIFIQUES AU CARROUSEL === */
    
    .hero-carousel {
        position: relative;
        width: 100%;
        height: 100vh;
        min-height: 600px;
        overflow: hidden;
        background: #0a0a0a;
    }

    .carousel-container {
        position: relative;
        width: 100%;
        height: 100%;
        display: flex;
        transition: transform 1s cubic-bezier(0.23, 1, 0.32, 1);
    }

    .carousel-slide {
        min-width: 100%;
        height: 100%;
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        background-attachment: fixed;
    }

    .slide-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(
            135deg,
            rgba(0, 0, 0, 0.7) 0%,
            rgba(0, 0, 0, 0.4) 30%,
            rgba(0, 0, 0, 0.6) 70%,
            rgba(0, 0, 0, 0.8) 100%
        );
        backdrop-filter: blur(1px);
    }

    .slide-content {
        position: relative;
        z-index: 3;
        text-align: center;
        color: white;
        max-width: 900px;
        padding: 0 30px;
        animation: slideInUp 1.2s cubic-bezier(0.23, 1, 0.32, 1);
    }

    .slide-pretitle {
        font-family: 'Inter', sans-serif;
        font-size: 0.95rem;
        font-weight: 500;
        letter-spacing: 3px;
        text-transform: uppercase;
        color: #d4af37;
        margin-bottom: 1rem;
        opacity: 0.9;
        animation: fadeInDown 1s ease-out 0.3s both;
    }

    .slide-title {
        font-family: 'Playfair Display', serif;
        font-size: clamp(2.5rem, 6vw, 4.5rem);
        font-weight: 700;
        line-height: 1.1;
        margin-bottom: 1.5rem;
        background: linear-gradient(135deg, #ffffff 0%, #f8f8f8 50%, #e8e8e8 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        text-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        animation: fadeInUp 1s ease-out 0.5s both;
    }

    .slide-subtitle {
        font-family: 'Inter', sans-serif;
        font-size: clamp(1.1rem, 2.5vw, 1.4rem);
        font-weight: 400;
        line-height: 1.7;
        margin-bottom: 2.5rem;
        color: rgba(255, 255, 255, 0.9);
        max-width: 700px;
        margin-left: auto;
        margin-right: auto;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        animation: fadeInUp 1s ease-out 0.7s both;
    }

    .slide-cta-secondary {
        background: rgba(255, 255, 255, 0.1);
        color: white;
        backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .slide-cta-secondary:hover {
        background: rgba(255, 255, 255, 0.2);
        box-shadow: 0 15px 40px rgba(255, 255, 255, 0.1);
    }

    /* Navigation améliorée */
    .carousel-nav {
        position: absolute;
        bottom: 40px;
        left: 50%;
        transform: translateX(-50%);
        display: flex;
        gap: 12px;
        z-index: 4;
        background: rgba(0, 0, 0, 0.3);
        padding: 12px 20px;
        border-radius: 30px;
        backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .nav-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.4);
        cursor: pointer;
        transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
        position: relative;
    }

    .nav-dot.active {
        background: #d4af37;
        transform: scale(1.4);
        box-shadow: 0 0 20px rgba(212, 175, 55, 0.6);
    }

    .nav-dot:hover:not(.active) {
        background: rgba(255, 255, 255, 0.7);
        transform: scale(1.2);
    }

    /* Flèches redessinées */
    .carousel-arrow {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        background: rgba(0, 0, 0, 0.4);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: white;
        font-size: 1.5rem;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        cursor: pointer;
        transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
        z-index: 4;
        backdrop-filter: blur(20px);
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: 'Inter', sans-serif;
    }

    .carousel-arrow:hover {
        background: rgba(212, 175, 55, 0.9);
        border-color: #d4af37;
        transform: translateY(-50%) scale(1.1);
        box-shadow: 0 10px 30px rgba(212, 175, 55, 0.3);
    }

    .carousel-arrow.prev {
        left: 40px;
    }

    .carousel-arrow.next {
        right: 40px;
    }

    /* Indicateur de progression */
    .progress-bar {
        position: absolute;
        bottom: 0;
        left: 0;
        height: 4px;
        background: #d4af37;
        transition: width 5s linear;
        z-index: 4;
    }

    /* Effets de particules améliorés */
    .floating-elements {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        z-index: 2;
        overflow: hidden;
    }

    .floating-element {
        position: absolute;
        width: 6px;
        height: 6px;
        background: rgba(212, 175, 55, 0.6);
        border-radius: 50%;
        animation: float-carousel 8s infinite ease-in-out;
    }

    .floating-element:nth-child(even) {
        background: rgba(255, 255, 255, 0.4);
        animation-duration: 12s;
    }

    .floating-element.diamond {
        width: 8px;
        height: 8px;
        background: transparent;
        border: 1px solid rgba(212, 175, 55, 0.5);
        border-radius: 0;
        transform: rotate(45deg);
    }

    /* Animations raffinées */
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

    /* Responsive Design Ultra-Optimisé */
    @media (max-width: 1200px) {
        .carousel-arrow {
            width: 50px;
            height: 50px;
            font-size: 1.2rem;
        }
        
        .carousel-arrow.prev {
            left: 30px;
        }
        
        .carousel-arrow.next {
            right: 30px;
        }
    }

    @media (max-width: 768px) {
        .hero-carousel {
            min-height: 550px;
        }
        
        .slide-content {
            padding: 0 20px;
        }
        
        .slide-pretitle {
            font-size: 0.8rem;
            letter-spacing: 2px;
        }
        
        .slide-cta-wrapper {
            flex-direction: column;
            align-items: center;
        }
        
        .slide-cta {
            padding: 14px 28px;
            font-size: 0.9rem;
            width: 100%;
            max-width: 280px;
            justify-content: center;
        }
        
        .carousel-arrow {
            width: 45px;
            height: 45px;
            font-size: 1rem;
        }
        
        .carousel-arrow.prev {
            left: 20px;
        }
        
        .carousel-arrow.next {
            right: 20px;
        }
        
        .carousel-nav {
            bottom: 30px;
            padding: 10px 16px;
        }
        
        .carousel-slide {
            background-attachment: scroll;
        }
    }

    @media (max-width: 480px) {
        .hero-carousel {
            min-height: 500px;
        }
        
        .slide-content {
            padding: 0 15px;
        }
        
        .slide-subtitle {
            margin-bottom: 2rem;
        }
        
        .carousel-arrow {
            width: 40px;
            height: 40px;
            font-size: 0.9rem;
        }
        
        .carousel-arrow.prev {
            left: 15px;
        }
        
        .carousel-arrow.next {
            right: 15px;
        }
        
        .carousel-nav {
            bottom: 25px;
            gap: 8px;
        }
        
        .nav-dot {
            width: 8px;
            height: 8px;
        }
    }

    /* Animations d'entrée pour les éléments */
    .slide-content > * {
        opacity: 0;
        animation-fill-mode: both;
    }

    /* Mode sombre pour les appareils qui le supportent */
    @media (prefers-color-scheme: dark) {
        .slide-overlay {
            background: linear-gradient(
                135deg,
                rgba(0, 0, 0, 0.8) 0%,
                rgba(0, 0, 0, 0.5) 30%,
                rgba(0, 0, 0, 0.7) 70%,
                rgba(0, 0, 0, 0.9) 100%
            );
        }
    }

    /* Performance optimizations */
    .carousel-slide {
        will-change: transform;
    }
    
    .carousel-container {
        will-change: transform;
    }
-wrapper {
        display: flex;
        gap: 1rem;
        justify-content: center;
        flex-wrap: wrap;
        animation: fadeInUp 1s ease-out 0.9s both;
    }

    .slide-cta {
        display: inline-flex;
        align-items: center;
        padding: 16px 32px;
        background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
        color: #1a1a1a;
        text-decoration: none;
        border-radius: 50px;
        font-family: 'Inter', sans-serif;
        font-weight: 600;
        font-size: 1rem;
        transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
        box-shadow: 0 10px 30px rgba(212, 175, 55, 0.3);
        text-transform: uppercase;
        letter-spacing: 1px;
        position: relative;
        overflow: hidden;
    }

    .slide-cta:hover {
        transform: translateY(-3px);
        box-shadow: 0 15px 40px rgba(212, 175, 55, 0.4);
        background: linear-gradient(135deg, #f4d03f 0%, #d4af37 100%);
    }

    .slide-cta-secondary {
        background: rgba(255, 255, 255, 0.1);
        color: white;
        backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
</style>

<!-- Section Hero Carrousel -->
<section id="hero" class="hero-carousel">
    <div class="progress-bar" id="progressBar"></div>
    
    <div class="carousel-container" id="carouselContainer">
        <!-- Slide 1 -->
        <div class="carousel-slide" style="background-image: linear-gradient(135deg, rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.4)), url('assets/img/slider1.jpg');">
            <div class="slide-overlay"></div>
            <div class="floating-elements">
                <div class="floating-element" style="left: 5%; top: 20%; animation-delay: 0s;"></div>
                <div class="floating-element" style="left: 15%; top: 60%; animation-delay: 2s;"></div>
                <div class="floating-element diamond" style="left: 25%; top: 30%; animation-delay: 4s;"></div>
                <div class="floating-element" style="left: 35%; top: 70%; animation-delay: 1s;"></div>
                <div class="floating-element" style="left: 45%; top: 10%; animation-delay: 3s;"></div>
                <div class="floating-element diamond" style="left: 55%; top: 50%; animation-delay: 5s;"></div>
                <div class="floating-element" style="left: 65%; top: 80%; animation-delay: 1.5s;"></div>
                <div class="floating-element" style="left: 75%; top: 25%; animation-delay: 3.5s;"></div>
                <div class="floating-element diamond" style="left: 85%; top: 65%; animation-delay: 0.5s;"></div>
                <div class="floating-element" style="left: 95%; top: 40%; animation-delay: 2.5s;"></div>
            </div>
            <div class="slide-content">
                <div class="slide-pretitle">Restaurant Mulho</div>
                <h1 class="slide-title">Saveurs Authentiques du Sénégal</h1>
                <p class="slide-subtitle">Découvrez une expérience culinaire exceptionnelle où tradition et modernité se rencontrent. Nos chefs passionnés préparent chaque plat avec des ingrédients frais et authentiques.</p>
                <div class="slide-cta-wrapper">
                    <a href="#menu" class="slide-cta">Découvrir le Menu</a>
                    <a href="#about" class="slide-cta slide-cta-secondary">Notre Histoire</a>
                </div>
            </div>
        </div>

        <!-- Slide 2 -->
        <div class="carousel-slide" style="background-image: linear-gradient(135deg, rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.4)), url('assets/img/slider2.jpg');">
            <div class="slide-overlay"></div>
            <div class="floating-elements">
                <div class="floating-element" style="left: 10%; top: 15%; animation-delay: 0.5s;"></div>
                <div class="floating-element diamond" style="left: 20%; top: 55%; animation-delay: 2.5s;"></div>
                <div class="floating-element" style="left: 30%; top: 35%; animation-delay: 4.5s;"></div>
                <div class="floating-element" style="left: 40%; top: 75%; animation-delay: 1.5s;"></div>
                <div class="floating-element diamond" style="left: 50%; top: 15%; animation-delay: 3.5s;"></div>
                <div class="floating-element" style="left: 60%; top: 55%; animation-delay: 5.5s;"></div>
                <div class="floating-element" style="left: 70%; top: 85%; animation-delay: 2s;"></div>
                <div class="floating-element diamond" style="left: 80%; top: 30%; animation-delay: 4s;"></div>
                <div class="floating-element" style="left: 90%; top: 70%; animation-delay: 1s;"></div>
            </div>
            <div class="slide-content">
                <div class="slide-pretitle">Ambiance Unique</div>
                <h1 class="slide-title">Un Cadre Chaleureux & Authentique</h1>
                <p class="slide-subtitle">Plongez dans une atmosphère conviviale qui célèbre la richesse culturelle du Sénégal. Parfait pour vos repas en famille, entre amis ou vos occasions spéciales.</p>
                <div class="slide-cta-wrapper">
                    <a href="#book-a-table" class="slide-cta">Réserver une Table</a>
                    <a href="galerie.php" class="slide-cta slide-cta-secondary">Voir la Galerie</a>
                </div>
            </div>
        </div>

        <!-- Slide 3 -->
        <div class="carousel-slide" style="background-image: linear-gradient(135deg, rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.4)), url('assets/img/slider3.jpg');">
            <div class="slide-overlay"></div>
            <div class="floating-elements">
                <div class="floating-element diamond" style="left: 8%; top: 25%; animation-delay: 1s;"></div>
                <div class="floating-element" style="left: 18%; top: 65%; animation-delay: 3s;"></div>
                <div class="floating-element" style="left: 28%; top: 45%; animation-delay: 5s;"></div>
                <div class="floating-element diamond" style="left: 38%; top: 85%; animation-delay: 2s;"></div>
                <div class="floating-element" style="left: 48%; top: 25%; animation-delay: 4s;"></div>
                <div class="floating-element" style="left: 58%; top: 65%; animation-delay: 6s;"></div>
                <div class="floating-element diamond" style="left: 68%; top: 95%; animation-delay: 2.5s;"></div>
                <div class="floating-element" style="left: 78%; top: 35%; animation-delay: 4.5s;"></div>
                <div class="floating-element" style="left: 88%; top: 75%; animation-delay: 1.5s;"></div>
            </div>
            <div class="slide-content">
                <div class="slide-pretitle">Événements Privés</div>
                <h1 class="slide-title">Célébrez Vos Moments Précieux</h1>
                <p class="slide-subtitle">Organisez vos célébrations, événements d'entreprise et réceptions dans un cadre exceptionnel. Notre équipe personnalise chaque détail pour créer des souvenirs inoubliables.</p>
                <div class="slide-cta-wrapper">
                    <a href="#events" class="slide-cta">Organiser un Événement</a>
                    <a href="#contact" class="slide-cta slide-cta-secondary">Nous Contacter</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation améliorée -->
    <div class="carousel-nav" id="carouselNav">
        <div class="nav-dot active" data-slide="0"></div>
        <div class="nav-dot" data-slide="1"></div>
        <div class="nav-dot" data-slide="2"></div>
    </div>

    <!-- Flèches -->
    <button class="carousel-arrow prev" id="prevBtn">‹</button>
    <button class="carousel-arrow next" id="nextBtn">›</button>
</section>

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
            
            // Gestion du hover
            const carousel = this.container.parentElement;
            carousel.addEventListener('mouseenter', () => {
                this.stopAutoPlay();
                this.stopProgress();
            });
            
            carousel.addEventListener('mouseleave', () => {
                this.startAutoPlay();
            });
            
            // Navigation clavier
            document.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowLeft') this.previousSlide();
                if (e.key === 'ArrowRight') this.nextSlide();
                if (e.key === ' ') {
                    e.preventDefault();
                    this.toggleAutoPlay();
                }
            });
            
            // Support tactile amélioré
            this.setupTouchSupport();
            
            // Gestion de la visibilité
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
    
    // Initialize carousel when DOM is loaded
    document.addEventListener('DOMContentLoaded', () => {
        new ProfessionalCarousel();
    });
    
    // Performance optimizations
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

