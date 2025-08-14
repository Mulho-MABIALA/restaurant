<?php
session_start();
include('lang.php');
require_once 'config.php';

try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Requête pour récupérer les horaires d'ouverture/fermeture par jour
    $query = "
        SELECT jour, heure_ouverture, heure_fermeture, ferme
        FROM horaires
        ORDER BY FIELD(jour, 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche')
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur SQL horaires : " . $e->getMessage());
    $results = []; // Valeur de repli
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Restaurant Mulho</title>
    <meta name="description" content="Restaurant Mulho - Découvrez nos plats de qualité">
    <meta name="keywords" content="restaurant, mulho, dakar, senegal">
    
    <!-- Favicons -->
    <link href="assets/img/favicon.png" rel="icon">
    <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Inter:wght@100;200;300;400;500;600;700;800;900&family=Amatic+SC:wght@400;700&display=swap" rel="stylesheet">

    <!-- CSS Files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/aos/aos.css" rel="stylesheet">
    <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
    <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            color: #333;
        }

        #preloader {
            display: none !important;
        }

        /* Section Hero */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        }

        .hero h1 {
            font-size: 3rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 2rem;
        }

        .btn-get-started {
            background: linear-gradient(135deg, #ec4899, #f97316);
            color: white;
            padding: 12px 30px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .btn-get-started:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(236, 72, 153, 0.3);
            color: white;
        }

        /* Sections */
        .section {
            padding: 80px 0;
        }

        .section-title {
            text-align: center;
            margin-bottom: 60px;
        }

        .section-title h2 {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 1rem;
        }

        .light-background {
            background-color: #f8fafc;
        }

        .dark-background {
            background-color: #1a202c;
            color: white;
        }

        /* Corrections pour que le contenu soit visible */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -15px;
        }

        .col-lg-5, .col-lg-7, .col-lg-4, .col-lg-8, .col-lg-3, .col-md-6 {
            padding: 0 15px;
            flex: 1;
        }

        .col-lg-5 { flex: 0 0 41.666667%; }
        .col-lg-7 { flex: 0 0 58.333333%; }
        .col-lg-4 { flex: 0 0 33.333333%; }
        .col-lg-8 { flex: 0 0 66.666667%; }

        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2rem;
            }
            
            .col-lg-5, .col-lg-7, .col-lg-4, .col-lg-8 {
                flex: 0 0 100%;
                margin-bottom: 2rem;
            }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.8; }
            50% { transform: scale(1.1); opacity: 0.4; }
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
        }

        :root {
            --primary: #ff6b35;
            --secondary: #f7931e;
            --accent: #ffd23f;
            --dark: #2d1810;
            --light: #faf8f5;
            --glass: rgba(255, 255, 255, 0.1);
            --shadow: rgba(0, 0, 0, 0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 50%, #16213e 100%);
            color: white;
            overflow-x: hidden;
        }

        /* Particles Background */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            width: 2px;
            height: 2px;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            border-radius: 50%;
            animation: float-particle 20s infinite linear;
            opacity: 0.6;
        }

        @keyframes float-particle {
            0% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 0.6;
            }
            90% {
                opacity: 0.6;
            }
            100% {
                transform: translateY(-100px) rotate(360deg);
                opacity: 0;
            }
        }

        /* Hero Section */
        .hero-section {
            min-height: 5vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .hero-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><defs><radialGradient id="grad1" cx="50%" cy="50%" r="50%"><stop offset="0%" style="stop-color:%23ff6b35;stop-opacity:0.1" /><stop offset="100%" style="stop-color:%23ff6b35;stop-opacity:0" /></radialGradient></defs><circle cx="200" cy="200" r="150" fill="url(%23grad1)" /><circle cx="800" cy="800" r="200" fill="url(%23grad1)" /></svg>');
            opacity: 0.3;
            animation: pulse-bg 8s ease-in-out infinite;
        }

        @keyframes pulse-bg {
            0%, 100% { transform: scale(1) rotate(0deg); }
            50% { transform: scale(1.1) rotate(180deg); }
        }

        .hero-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2.5rem, 8vw, 6rem);
            font-weight: 900;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 50%, var(--accent) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-align: center;
            margin-bottom: 2rem;
            position: relative;
            z-index: 2;
        }

        .hero-subtitle {
            font-size: 1.5rem;
            text-align: center;
            margin-bottom: 3rem;
            opacity: 0.8;
            font-weight: 300;
        }

        /* Glass Cards */
        .glass-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 2rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .glass-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: left 0.8s;
        }

        .glass-card:hover::before {
            left: 100%;
        }

        .glass-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 20px 40px rgba(255, 107, 53, 0.2);
            border-color: rgba(255, 107, 53, 0.3);
        }

        /* Image Container with 3D Effect */
        .image-3d {
            position: relative;
            border-radius: 20px;
            overflow: hidden;
            transform-style: preserve-3d;
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .image-3d:hover {
            transform: rotateY(5deg) rotateX(5deg) translateZ(50px);
        }

        .image-3d img {
            width: 100%;
            height: 400px;
            object-fit: cover;
            transition: all 0.6s ease;
        }

        .image-3d:hover img {
            transform: scale(1.1);
            filter: brightness(1.1) contrast(1.1);
        }

        /* Floating Elements */
        .floating-element {
            position: absolute;
            animation: float 6s ease-in-out infinite;
        }

        .floating-element:nth-child(odd) {
            animation-delay: -3s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
        }

        /* Stats with Neon Effect */
        .neon-stat {
            text-align: center;
            padding: 1.5rem;
            background: rgba(255, 107, 53, 0.1);
            border: 2px solid rgba(255, 107, 53, 0.3);
            border-radius: 16px;
            position: relative;
            overflow: hidden;
            transition: all 0.4s ease;
        }

        .neon-stat:hover {
            box-shadow: 0 0 30px rgba(255, 107, 53, 0.4);
            border-color: var(--primary);
        }

        .neon-stat::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, var(--primary), var(--secondary), var(--accent));
            border-radius: 16px;
            z-index: -1;
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        .neon-stat:hover::before {
            opacity: 1;
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-top: 0.5rem;
        }

        /* Modern Features List */
        .feature-modern {
            display: flex;
            align-items: center;
            margin: 1.5rem 0;
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 16px;
            border-left: 4px solid var(--primary);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .feature-modern::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 0;
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            transition: width 0.4s ease;
            opacity: 0.1;
        }

        .feature-modern:hover::before {
            width: 100%;
        }

        .feature-modern:hover {
            transform: translateX(10px);
            background: rgba(255, 107, 53, 0.08);
            border-left-color: var(--accent);
        }

        .feature-icon-modern {
            font-size: 2rem;
            margin-right: 1.5rem;
            color: var(--primary);
            transition: all 0.4s ease;
        }

        .feature-modern:hover .feature-icon-modern {
            transform: scale(1.2) rotate(10deg);
            color: var(--accent);
        }

        /* CTA Button with Glow */
        .cta-glow {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            padding: 1rem 2rem;
            border-radius: 50px;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(255, 107, 53, 0.3);
        }

        .cta-glow::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.6s;
        }

        .cta-glow:hover::before {
            left: 100%;
        }

        .cta-glow:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 15px 35px rgba(255, 107, 53, 0.5);
            color: white;
        }

        /* Morphing Shapes */
        .morph-shape {
            position: absolute;
            width: 200px;
            height: 200px;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            border-radius: 30% 70% 70% 30% / 30% 30% 70% 70%;
            opacity: 0.1;
            animation: morph 8s ease-in-out infinite;
        }

        @keyframes morph {
            0%, 100% {
                border-radius: 30% 70% 70% 30% / 30% 30% 70% 70%;
                transform: rotate(0deg) scale(1);
            }
            25% {
                border-radius: 58% 42% 75% 25% / 76% 46% 54% 24%;
                transform: rotate(90deg) scale(1.1);
            }
            50% {
                border-radius: 50% 50% 33% 67% / 55% 27% 73% 45%;
                transform: rotate(180deg) scale(0.9);
            }
            75% {
                border-radius: 33% 67% 58% 42% / 63% 68% 32% 37%;
                transform: rotate(270deg) scale(1.05);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-section {
                padding: 2rem 0;
            }
            
            .glass-card {
                padding: 1.5rem;
                margin: 1rem 0;
            }
            
            .image-3d img {
                height: 300px;
            }
            
            .stat-number {
                font-size: 2rem;
            }
        }

        /* Scroll Animations */
        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }

        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

        * {
            font-family: 'Inter', sans-serif;
        }

        .contact-section {
            padding: 60px 0;
            background: #f8fafc;
            position: relative;
        }

        .section-title {
            text-align: center;
            margin-bottom: 50px;
            color: #2d3748;
            font-weight: 800;
            font-size: 3rem;
            letter-spacing: -1px;
            background: linear-gradient(135deg, #ff6b6b, #ee5a24, #feca57);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .info-card {
            background: white;
            border: 1px solid #e2e8f0;
            padding: 35px;
            border-radius: 20px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 25px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .info-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.8s ease;
        }

        .info-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(255, 107, 107, 0.15);
            border-color: #ff6b6b;
        }

        .info-icon {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24, #feca57);
            color: white;
            width: 80px;
            height: 80px;
            border-radius: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            flex-shrink: 0;
            box-shadow: 0 15px 35px rgba(255, 107, 107, 0.4);
            position: relative;
        }

        .info-icon::after {
            content: '';
            position: absolute;
            inset: -3px;
            background: linear-gradient(135deg, #ff6b6b, #ee5a24, #feca57);
            border-radius: 28px;
            z-index: -1;
            opacity: 0;
            transition: opacity 0.4s ease;
            filter: blur(8px);
        }

        .info-card:hover .info-icon::after {
            opacity: 0.6;
        }

        .info-content h3 {
            color: #2d3748;
            font-weight: 700;
            margin-bottom: 8px;
            font-size: 1.3rem;
            letter-spacing: -0.5px;
        }

        .info-content p,
        .info-content a {
            color: #4a5568;
            margin: 0;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .info-content a:hover {
            color: #ff6b6b;
            transform: translateX(3px);
        }

        .opening-hours {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .opening-hours li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(0,0,0,0.08);
            font-size: 0.95rem;
        }

        .opening-hours li:last-child {
            border-bottom: none;
        }

        .day-name {
            font-weight: 700;
            color: #2d3748;
            letter-spacing: -0.3px;
        }

        .hours {
            color: #4a5568;
            font-weight: 500;
        }

        .closed {
            color: #e53e3e;
            font-weight: 700;
        }

        .contact-form {
            background: white;
            border: 1px solid #e2e8f0;
            padding: 50px;
            border-radius: 25px;
            margin-top: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            position: relative;
        }

        .contact-form::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #ff6b6b, #ee5a24, #feca57, #5f27cd, #00d2d3);
            background-size: 300% 100%;
            animation: gradient 3s ease infinite;
        }

        @keyframes gradient {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        .form-title {
            text-align: center;
            margin-bottom: 40px;
            color: #2d3748;
            font-weight: 800;
            font-size: 2.5rem;
            letter-spacing: -1px;
            position: relative;
        }

        .form-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 4px;
            background: linear-gradient(90deg, #ff6b6b, #feca57);
            border-radius: 2px;
        }

        .form-group {
            position: relative;
            margin-bottom: 25px;
        }

        .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 18px 20px;
            font-size: 1rem;
            font-weight: 500;
            background: #f8fafc;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #ff6b6b;
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.1);
            background: white;
            outline: none;
        }

        .form-control::placeholder {
            color: #9ca3af;
            font-weight: 400;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 140px;
        }

        .submit-btn {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24, #feca57);
            background-size: 200% 200%;
            color: white;
            border: none;
            padding: 18px 50px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            width: 100%;
            position: relative;
            overflow: hidden;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 15px 35px rgba(255, 107, 107, 0.4);
        }

        .submit-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.6s ease;
        }

        .submit-btn:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 20px 40px rgba(255, 107, 107, 0.6);
            background-position: right center;
        }

        .submit-btn:hover::before {
            left: 100%;
        }

        .submit-btn:active {
            transform: translateY(-1px) scale(1.01);
        }

        .message-status {
            text-align: center;
            margin-top: 20px;
            padding: 15px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
        }

        .loading { 
            color: #ff6b6b; 
            background: rgba(255, 107, 107, 0.1);
            border: 2px solid rgba(255, 107, 107, 0.2);
        }
        .error-message { 
            color: #e53e3e; 
            background: rgba(229, 62, 62, 0.1);
            border: 2px solid rgba(229, 62, 62, 0.2);
        }
        .sent-message { 
            color: #38a169; 
            background: rgba(56, 161, 105, 0.1);
            border: 2px solid rgba(56, 161, 105, 0.2);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .contact-section {
                padding: 40px 0;
            }

            .section-title {
                font-size: 2rem;
                margin-bottom: 40px;
            }

            .info-card {
                flex-direction: column;
                text-align: center;
                padding: 30px;
                gap: 20px;
            }

            .info-icon {
                width: 70px;
                height: 70px;
                font-size: 1.8rem;
            }

            .contact-form {
                padding: 35px 25px;
                margin-top: 30px;
            }

            .form-title {
                font-size: 2rem;
                margin-bottom: 30px;
            }

            .opening-hours li {
                flex-direction: column;
                gap: 5px;
                text-align: center;
                padding: 12px 0;
            }
        }

        @media (max-width: 480px) {
            .info-card {
                padding: 25px 20px;
            }

            .contact-form {
                padding: 30px 20px;
            }

            .form-control {
                padding: 15px 16px;
            }

            .submit-btn {
                padding: 16px 40px;
                font-size: 1rem;
            }
        }

        /* Animations d'entrée */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .info-card {
            animation: fadeInUp 0.8s ease forwards;
        }

        .contact-form {
            animation: fadeInUp 1s ease forwards;
        }
    </style>
</head>
<body class="index-page">
    <?php include('includes/navbar.php'); ?>
    
    <!-- Inclusion du carrousel -->
    <?php include('includes/carrousel.php'); ?>

    <!-- Particles Background -->
    <div class="particles"></div>

    <!-- Morphing Shapes -->
    <div class="morph-shape" style="top: 10%; right: 10%;"></div>
    <div class="morph-shape" style="bottom: 20%; left: 15%; animation-delay: -4s;"></div>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-bg"></div>
        <div class="container">
            <div class="text-center">
                <h1 class="hero-title fade-in">À propos de Mulho</h1>
                <p class="hero-subtitle fade-in">Où l'authenticité sénégalaise rencontre l'excellence culinaire</p>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <section class="py-5">
        <div class="container">
            <div class="row g-5 align-items-center">
                <!-- Image Side -->
                <div class="col-lg-6">
                    <div class="glass-card fade-in">
                        <div class="image-3d">
                            <img src="assets/img/apropos.jpg" alt="Restaurant Mulho">
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="row g-3 mt-4 fade-in">
                        <div class="col-4">
                            <div class="neon-stat">
                                <div class="stat-number" data-count="15">0</div>
                                <div class="stat-label">Années</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="neon-stat">
                                <div class="stat-number" data-count="50">0</div>
                                <div class="stat-label">Plats</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="neon-stat">
                                <div class="stat-number" data-count="1000">0</div>
                                <div class="stat-label">Clients</div>
                            </div>
                        </div>
                    </div>

                    <!-- CTA -->
                    <div class="text-center mt-4 fade-in">
                        <a href="tel:787308706" class="cta-glow">
                            <i class="bi bi-telephone-fill"></i>
                            Réserver : 78 730 87 06
                        </a>
                    </div>
                </div>

                <!-- Content Side -->
                <div class="col-lg-6">
                    <div class="glass-card fade-in">
                        <h2 class="mb-4" style="font-family: 'Playfair Display', serif; font-size: 2.5rem; background: linear-gradient(135deg, var(--primary), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                            Notre Histoire
                        </h2>
                        
                        <p class="mb-4" style="font-size: 1.2rem; line-height: 1.8; opacity: 0.9;">
                            Bienvenue au Restaurant Mulho, où chaque plat raconte l'histoire passionnée de la gastronomie sénégalaise. 
                            Situé au cœur vibrant de Dakar, nous créons des expériences culinaires qui éveillent les sens et 
                            célèbrent l'authenticité de notre terroir.
                        </p>

                        <!-- Features -->
                        <div class="features-list">
                            <div class="feature-modern fade-in">
                                <i class="bi bi-gem feature-icon-modern"></i>
                                <div>
                                    <strong>Ingrédients Premium</strong><br>
                                    <span style="opacity: 0.8;">Sélection rigoureuse de produits locaux d'exception</span>
                                </div>
                            </div>

                            <div class="feature-modern fade-in">
                                <i class="bi bi-fire feature-icon-modern"></i>
                                <div>
                                    <strong>Cuisine Authentique</strong><br>
                                    <span style="opacity: 0.8;">Techniques traditionnelles sublimées par l'innovation</span>
                                </div>
                            </div>

                            <div class="feature-modern fade-in">
                                <i class="bi bi-hearts feature-icon-modern"></i>
                                <div>
                                    <strong>Expérience Unique</strong><br>
                                    <span style="opacity: 0.8;">Service personnalisé dans une atmosphère chaleureuse</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 p-4 fade-in" style="background: rgba(255, 107, 53, 0.1); border-radius: 16px; border-left: 4px solid var(--primary);">
                            <p style="margin: 0; font-style: italic; opacity: 0.9;">
                                "Notre passion transcende la simple restauration. Nous créons des moments magiques où chaque bouchée 
                                transporte nos invités dans un voyage sensoriel au cœur de l'âme sénégalaise."
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php include('evenements.php'); ?>
    <!-- Section Réserver une table -->
    <section id="book-a-table" class="book-a-table section">
        <div class="container section-title" data-aos="fade-up">
            <h2>Réserver une table</h2>
            <p><span>Réservez votre</span> <span class="description-title">Table</span></p>
        </div>
        <div class="container">
            <div class="row g-0" data-aos="fade-up" data-aos-delay="100">
                <div class="col-lg-4 reservation-img" style="background-image: url(assets/img/reservation.jpg); background-size: cover; background-position: center; min-height: 400px;"></div>
                <div class="col-lg-8 d-flex align-items-center" style="background: #f8fafc; padding: 60px 40px;" data-aos="fade-up" data-aos-delay="200">
                    <form action="forms/book-a-table.php" method="post" role="form" class="php-email-form" style="width: 100%;">
                        <div class="row gy-4">
                            <div class="col-lg-4 col-md-6">
                                <input type="text" name="name" class="form-control" id="name" placeholder="Votre nom" required style="border: 2px solid #e2e8f0; border-radius: 10px; padding: 12px;">
                            </div>
                            <div class="col-lg-4 col-md-6">
                                <input type="email" class="form-control" name="email" id="email" placeholder="Votre email" required style="border: 2px solid #e2e8f0; border-radius: 10px; padding: 12px;">
                            </div>
                            <div class="col-lg-4 col-md-6">
                                <input type="text" class="form-control" name="phone" id="phone" placeholder="Votre téléphone" required style="border: 2px solid #e2e8f0; border-radius: 10px; padding: 12px;">
                            </div>
                            <div class="col-lg-4 col-md-6">
                                <input type="date" name="date" class="form-control" id="date" placeholder="Date" required style="border: 2px solid #e2e8f0; border-radius: 10px; padding: 12px;">
                            </div>
                            <div class="col-lg-4 col-md-6">
                                <input type="time" class="form-control" name="time" id="time" placeholder="Heure" required style="border: 2px solid #e2e8f0; border-radius: 10px; padding: 12px;">
                            </div>
                            <div class="col-lg-4 col-md-6">
                                <input type="number" class="form-control" name="people" id="people" placeholder="Nombre de personnes" required style="border: 2px solid #e2e8f0; border-radius: 10px; padding: 12px;">
                            </div>
                        </div>
                        <div class="form-group mt-3">
                            <textarea class="form-control" name="message" rows="5" placeholder="Message" style="border: 2px solid #e2e8f0; border-radius: 10px; padding: 12px; width: 100%;"></textarea>
                        </div>
                        <div class="text-center mt-3">
                            <div class="loading" style="display: none;">Chargement</div>
                            <div class="error-message" style="display: none; color: #e53e3e;"></div>
                            <div class="sent-message" style="display: none; color: #38a169;">Votre demande de réservation a été envoyée. Nous vous rappellerons ou enverrons un email pour confirmer votre réservation. Merci !</div>
                            <button type="submit" style="background: linear-gradient(135deg, #ec4899, #f97316); color: white; border: none; padding: 15px 40px; border-radius: 50px; font-weight: 600; cursor: pointer; transition: all 0.3s ease;">Réserver une table</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Section Contact -->
    <section id="contact" class="contact section">
        <div class="container section-title" data-aos="fade-up">
            <h2>Contact</h2>
            <p><span>Besoin d'aide ?</span> <span class="description-title">Contactez-nous</span></p>
        </div>
        <div class="container" data-aos="fade-up" data-aos-delay="100">
            <div class="mb-5">
                <iframe style="width: 100%; height: 400px; border-radius: 15px;" 
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3858.9689555935147!2d-17.44270312595434!3d14.693425085886857!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0xec10d6c8b7e6c13%3A0x20e6e5b6b7e6c13!2sMedina%2C%20Dakar%2C%20Senegal!5e0!3m2!1sen!2sus!4v1641234567890!5m2!1sen!2sus" 
                        frameborder="0" allowfullscreen=""></iframe>
            </div>
            
            <div class="contact-section">
                <div class="container">
                    <h1 class="section-title">Contactez-nous</h1>
                    
                    <div class="row gy-4">
                        <div class="col-md-6">
                            <div class="info-card" data-aos="fade-up" data-aos-delay="200">
                                <div class="info-icon">
                                    <i class="bi bi-geo-alt"></i>
                                </div>
                                <div class="info-content">
                                    <h3>Adresse</h3>
                                    <p>Dakar, Medina rue 27x24</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="info-card" data-aos="fade-up" data-aos-delay="300">
                                <div class="info-icon">
                                    <i class="bi bi-telephone"></i>
                                </div>
                                <div class="info-content">
                                    <h3>Appelez-nous</h3>
                                    <p><a href="tel:787308706">78 730 87 06</a></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="info-card" data-aos="fade-up" data-aos-delay="400">
                                <div class="info-icon">
                                    <i class="bi bi-envelope"></i>
                                </div>
                                <div class="info-content">
                                    <h3>Envoyez-nous un email</h3>
                                    <p><a href="mailto:mulhomabiala29@gmail.com">mulhomabiala29@gmail.com</a></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="info-card" data-aos="fade-up" data-aos-delay="500">
                                <div class="info-icon">
                                    <i class="bi bi-clock"></i>
                                </div>
                                <div class="info-content">
                                    <h3>Heures d'ouverture</h3>
                                    <ul class="opening-hours">
                                        <?php if (!empty($results)): ?>
                                            <?php foreach ($results as $row): ?>
                                                <li>
                                                    <span class="day-name"><?= htmlspecialchars($row['jour']) ?></span>
                                                    <span class="hours">
                                                        <?php if ($row['ferme'] == 1): ?>
                                                            <span class="closed">Fermé</span>
                                                        <?php else: ?>
                                                            <?= htmlspecialchars(substr($row['heure_ouverture'], 0, 5)) ?> -
                                                            <?= htmlspecialchars(substr($row['heure_fermeture'], 0, 5)) ?>
                                                        <?php endif; ?>
                                                    </span>
                                                </li>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <li>
                                                <span class="hours">Aucun horaire trouvé.</span>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Formulaire de contact -->
                    <form action="forms/contact.php" method="post" class="php-email-form contact-form" data-aos="fade-up" data-aos-delay="600">
                        <h2 class="form-title">Envoyez-nous un message</h2>
                        
                        <div class="row gy-4">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <input type="text" name="name" class="form-control" placeholder="Votre nom complet" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <input type="email" class="form-control" name="email" placeholder="Votre adresse email" required>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <input type="text" class="form-control" name="subject" placeholder="Sujet de votre message" required>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <textarea class="form-control" name="message" rows="6" placeholder="Votre message détaillé..." required style="resize: vertical;"></textarea>
                                </div>
                            </div>
                            <div class="col-md-12 text-center">
                                <div class="loading message-status" style="display: none;">Envoi en cours...</div>
                                <div class="error-message message-status" style="display: none;"></div>
                                <div class="sent-message message-status" style="display: none;">Votre message a été envoyé avec succès ! Merci de nous avoir contactés.</div>
                                <button type="submit" class="submit-btn">Envoyer le message</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <?php include('includes/footer.php'); ?>

    <!-- Scroll Top -->
    <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center" style="position: fixed; bottom: 30px; right: 30px; background: linear-gradient(135deg, #ec4899, #f97316); color: white; width: 50px; height: 50px; border-radius: 50%; text-decoration: none; box-shadow: 0 5px 15px rgba(236, 72, 153, 0.3); transition: all 0.3s ease; z-index: 999; display: none;">
        <i class="bi bi-arrow-up-short" style="font-size: 1.5rem;"></i>
    </a>

    <!-- Scripts -->
    <script src="cart.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // === 🔁 Mise à jour du panier ===
        function updateCartCount() {
            const cartCount = document.getElementById('cart-count');
            if (!cartCount) return;
            try {
                const cart = JSON.parse(localStorage.getItem('cart')) || [];
                const count = cart.reduce((sum, item) => sum + (item.quantity || 0), 0);
                cartCount.textContent = count;
            } catch (e) {
                console.error("Erreur panier :", e);
                cartCount.textContent = "0";
            }
        }
        updateCartCount();

        // === 🌐 Changement de langue ===
        function changeLanguage(lang) {
            console.log('Langue sélectionnée:', lang);
            window.location.search = `?lang=${lang}`;
        }
        window.changeLanguage = changeLanguage;

        // === 🔗 Scroll fluide vers les ancres ===
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });

        // === 🔼 Bouton scroll to top ===
        const scrollTop = document.getElementById('scroll-top');
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                scrollTop.style.display = 'flex';
            } else {
                scrollTop.style.display = 'none';
            }
        });

        scrollTop?.addEventListener('click', (e) => {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        // === 🍽️ Fonction d'ajout au panier ===
        window.ajouterAuPanier = function(productId) {
            // Cette fonction devrait être définie dans cart.js
            console.log('Ajout au panier:', productId);
            // Simuler l'ajout
            updateCartCount();
        };

        // === 📅 Animation AOS (si disponible) ===
        if (typeof AOS !== 'undefined') {
            AOS.init({
                duration: 1000,
                easing: 'ease-in-out',
                once: true,
                mirror: false
            });
        }
    });
    </script>

    <script>
        // Create particles
        function createParticles() {
            const particlesContainer = document.querySelector('.particles');
            const particleCount = 50;

            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 20 + 's';
                particle.style.animationDuration = (Math.random() * 10 + 15) + 's';
                particlesContainer.appendChild(particle);
            }
        }

        // Animate stats
        function animateStats() {
            const stats = document.querySelectorAll('[data-count]');
            stats.forEach(stat => {
                const target = parseInt(stat.getAttribute('data-count'));
                let current = 0;
                const increment = target / 100;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    stat.textContent = Math.floor(current) + (target >= 1000 ? '+' : '');
                }, 50);
            });
        }

        // Scroll animations
        function setupScrollAnimations() {
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        if (entry.target.querySelector('[data-count]')) {
                            setTimeout(animateStats, 300);
                        }
                    }
                });
            }, observerOptions);

            document.querySelectorAll('.fade-in').forEach(el => {
                observer.observe(el);
            });
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            createParticles();
            setupScrollAnimations();
            
            // Add initial visible class to hero elements
            setTimeout(() => {
                document.querySelectorAll('.hero-section .fade-in').forEach(el => {
                    el.classList.add('visible');
                });
            }, 100);
        });

        // Mouse parallax effect
        document.addEventListener('mousemove', (e) => {
            const mouseX = e.clientX / window.innerWidth;
            const mouseY = e.clientY / window.innerHeight;
            
            document.querySelectorAll('.morph-shape').forEach((shape, index) => {
                const speed = (index + 1) * 0.02;
                const x = (mouseX - 0.5) * speed * 100;
                const y = (mouseY - 0.5) * speed * 100;
                shape.style.transform += ` translate(${x}px, ${y}px)`;
            });
        });
    </script>

    <!-- Vendor JS Files -->
    <script src="assets/vendor/purecounter/purecounter_vanilla.js"></script>
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/php-email-form/validate.js"></script>
    <script src="assets/vendor/aos/aos.js"></script>
    <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
    <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
    <!-- Main JS File -->
    <script src="assets/js/main.js"></script>
</body>
</html>