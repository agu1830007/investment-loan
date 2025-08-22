<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home Page</title>
    <link rel="stylesheet" href="home.css">
    <style>
      html {
        scroll-behavior: smooth;
        scroll-padding-top: 7rem; /* Adjust to match your header height */
      }
      @media (max-width: 900px) {
        html {
          scroll-padding-top: 8rem; /* Adjust for taller mobile header if needed */
        }
      }
    </style>
</head>

<body>
    <style>
        @media(min-width: 1000px){
            #hero .hero-content{
                margin-left: 18rem;
                max-width: 60%;
              
                button{
                    margin-left: 17rem;
                }
            }
             #hero .hero-content p {
                max-width: 100%;
            }
        }
 
@media (max-width: 900px) {
  #hero {
    min-height: 65rem;
    height: auto;
    width: 100vw;
    background-size: cover;
    background-repeat: no-repeat;
    background-position: center center;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.5rem;
  height: 500rem;
    box-shadow: 0 4px 18px rgba(35,57,93,0.10);
    position: relative;
    z-index: 2;
    padding: 0;
    margin-top: -0.1rem;
    margin-bottom: 30rem;
    
  }
  .hero-content {
    width: 100%;
    max-width: 98vw;
    box-sizing: border-box;
    padding: 2.2rem 1.1rem 1.5rem 1.1rem;
    border-radius: 1.1rem;
    margin: 2.5rem 0.5rem 0.5rem 0.5rem;
    box-shadow: 0 2px 12px rgba(35,57,93,0.08);
    position: relative;
    z-index: 3;
    margin-bottom: 10rem;
    margin-top: 9rem;
    
  }
  .head-nav{
    background: #fff;
    border-radius: 0;
    height: 7rem;
    margin-bottom: 1rem;
  }
 .sitename{
    background-color: #fff;
 }
.nav-toggle span{
    background: #fff;
}
header{
    margin-bottom: 0;
}

  .about-modern p{
    max-width: 90.5vw;
  }
  .about-modern .aboutt{
    max-width: 80vw;
  }
 #about .about-image-modern{
    img{
    max-width: 15px;
    }
  }
  
 
#hero{
    max-width: 98vw;
}
#testimonials .section-image{
    img{
        margin-left: -0.05rem;
        max-width: 280px;
    }
}
.loan-content-modern .loanss{
    max-width: 270px;
}
#contact{
    .section-content{
    border-radius: none;
    border-radius: 0;
    }
}
.dropdown-content{
    background-color: #fff;
    position: static;
    width: 100%;
    display: none;
}
@media (max-width: 900px) {
  .dropdown-content.show {
    display: block;
  }
}
@media (min-width: 901px) {
  .dropdown-content {
    display: none !important;
    position: absolute;
    left: 0;
    top: 100%;
    min-width: 180px;
    z-index: 10;
    box-shadow: 0 2px 8px rgba(35,57,93,0.07);
  }
  .dropdown:hover > .dropdown-content {
    display: block !important;
    
  }


}


}



  


</style>
    <main>
        <header class="head-nav" >

            <a href="home.php">
                <h1 class="sitename" style="margin-top: -1.5rem;">Excel Investments</h1>
            </a>
            <button class="nav-toggle" id="navToggle" aria-label="Open navigation">
                <span class="hamburger-icon">
                    <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect y="6" width="28" height="3" rx="1.5" fill="#1a237e"/>
                        <rect y="13" width="28" height="3" rx="1.5" fill="#1a237e"/>
                        <rect y="20" width="28" height="3" rx="1.5" fill="#1a237e"/>
                    </svg>
                </span>
                <span class="close-icon" style="display:none;">
                    <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <line x1="7" y1="7" x2="21" y2="21" stroke="#1a237e" stroke-width="3" stroke-linecap="round"/>
                        <line x1="21" y1="7" x2="7" y2="21" stroke="#1a237e" stroke-width="3" stroke-linecap="round"/>
                    </svg>
                </span>
            </button>
            <div class="nav-menu" style="margin-left: 27rem;">
                <nav>
                    <ul id="mainNav">

                        <li><a href="#hero">Home</a></li>
                        <li><a href="#about">About Us</a></li>
                        <li><a href="#services">Services</a></li>
                       
                                                <li><a href="#contact">Contact Us</a></li>

                        <li class="dropdown">
                            <a href="#">
  <span style="display:inline-flex;align-items:center;">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="margin-right:6px;vertical-align:middle;">
      <circle cx="12" cy="8" r="4" stroke="#1a237e" stroke-width="2" fill="#eafaf1"/>
      <path d="M4 20c0-3.3 3.6-6 8-6s8 2.7 8 6" stroke="#1a237e" stroke-width="2" fill="none"/>
    </svg>
  </span>
  <i class="bi bi-chevron-down"></i>
</a>
                            <ul class="dropdown-content">
                                <li><a href="register.php">Register</a></li>
                                <li><a href="login.php">Login</a></li>
                            </ul>
                        </li>
                        </li>

                    </ul>
                </nav>

                <script>
                document.addEventListener('DOMContentLoaded', function() {
  var navToggle = document.getElementById('navToggle');
  var navMenu = document.querySelector('.nav-menu');
  var hamburgerIcon = navToggle.querySelector('.hamburger-icon');
  var closeIcon = navToggle.querySelector('.close-icon');

  // Hamburger menu toggle
  if (navToggle && navMenu) {
    navToggle.addEventListener('click', function(e) {
      var isOpen = navMenu.classList.toggle('open');
      hamburgerIcon.style.display = isOpen ? 'none' : '';
      closeIcon.style.display = isOpen ? '' : 'none';
      // When opening menu, close all dropdowns
      if (isOpen) {
        document.querySelectorAll('.dropdown-content').forEach(function(menu) {
          menu.classList.remove('show');
        });
      }
    });

    // Only close menu when a real link is clicked (not dropdown parent)
    navMenu.querySelectorAll('a').forEach(function(link) {
      link.addEventListener('click', function(e) {
        // If this link is a dropdown parent, do not close menu
        if (this.parentElement.classList.contains('dropdown')) {
          e.preventDefault();
          return;
        }
        // Otherwise, close menu
        navMenu.classList.remove('open');
        hamburgerIcon.style.display = '';
        closeIcon.style.display = 'none';
      });
    });
  }

  // Dropdown toggle for mobile only (when nav-menu is open)
  document.querySelectorAll('.dropdown > a').forEach(function(dropdownLink) {
    dropdownLink.addEventListener('click', function(e) {
      // Only toggle dropdown if navMenu is open (mobile)
      if (!navMenu.classList.contains('open')) return;
      e.preventDefault();
      var dropdownMenu = this.nextElementSibling;
      if (dropdownMenu && dropdownMenu.classList.contains('dropdown-content')) {
        // Close all other open dropdowns first
        document.querySelectorAll('.dropdown-content.show').forEach(function(openMenu) {
          if (openMenu !== dropdownMenu) openMenu.classList.remove('show');
        });
        dropdownMenu.classList.toggle('show');
      }
      e.stopPropagation();
    });
  });

  // Prevent closing dropdown when clicking inside dropdown-content on mobile
  document.querySelectorAll('.dropdown-content').forEach(function(menu) {
    menu.addEventListener('click', function(e) {
      if (navMenu.classList.contains('open')) {
        e.stopPropagation();
      }
    });
  });

  // Close dropdowns if clicking outside (on mobile only)
  document.addEventListener('click', function(e) {
    if (!navMenu.classList.contains('open')) return;
    document.querySelectorAll('.dropdown-content.show').forEach(function(openMenu) {
      openMenu.classList.remove('show');
    });
  });
});
</script>
            </div>
        </header>
        <section id="hero"
            style="background-image: url('images/timo-volz-KsG9313lOPM-unsplash.jpg'); background-size: cover; background-position: center;height: 100vh; display: flex; align-items: center; justify-content: center; text-align: center; margin-bottom:0; padding-bottom:0;">
            <div class="hero-content" >
                <h1>Invest Smarter, Grow Your Wealth</h1>

                <p><strong>At Excel Investment,We empower investors like you to achieve
                        their financial freedom and objectives,Take control now of your financial future
                        with
                        Excel Investment,Our innovative solutions and expert guidance help you make informed
                        decisions and grow your wealth massively.Take a step now to move in to a more
                        beautiful and secured future.</strong>
                </p>
                <button class="hero-btn"> <a href="#contact" class="btn">Get Started</a> </button>
            </div>

        </section>
        <section id="about">
            <div class="about-modern" style="display: flex; flex-wrap: wrap; align-items: center; gap: 2.5rem; background: linear-gradient(120deg, #f5f7fa 60%, #e3e9f7 100%);  box-shadow: 0 4px 24px rgba(35,57,93,0.08); margin-top:0; margin-bottom:2rem; padding: 2.5rem 1.5rem;">
                <div class="about-content-modern" style="flex: 2 1 400px; min-width: 320px; display: flex; flex-direction: column; justify-content: center;">
                    <h2 style="color: #23395d; font-size: 2.2rem; font-weight: 700; margin-bottom: 1rem; letter-spacing: 1px;">About Us</h2>
                    <p style="font-size: 1.15rem; color: #2d3a4a; margin-bottom: 1.1rem; line-height: 1.7;">Excel Investment is a leading financial services provider dedicated to helping individuals and businesses achieve their financial goals. With a team of experienced professionals, we offer a range of investment plans, loan services, and financial advisory to empower our clients to make informed decisions and secure their financial future.</p>
                    <div class="aboutt" style="display: flex; flex-wrap: wrap; gap: 1.5rem; margin-bottom: 1.1rem;">
                        <div style="background: #fff; border-radius: 1rem; box-shadow: 0 2px 8px rgba(35,57,93,0.07); padding: 1.2rem 1.5rem; flex: 1 1 180px; min-width: 180px;">
                            <h4 style="color: #274472; margin-bottom: 0.5rem; font-size: 1.1rem;">Our Mission</h4>
                            <p style="margin: 0; color: #23395d; font-size: 1rem;">To provide innovative financial solutions tailored to your unique needs, building long-term relationships based on trust, transparency, and excellence.</p>
                        </div>
                        <div  class="about"background: #fff; border-radius: 1rem; box-shadow: 0 2px 8px rgba(35,57,93,0.07); padding: 1.2rem 1.5rem; flex: 1 1 180px; min-width: 180px;">
                            <h4 style="color: #274472; margin-bottom: 0.5rem; font-size: 1.1rem;padding-left: 15px;">Our Values</h4>
                            <ul style="list-style: disc; padding-left: 1.2em; margin: 0.7em 0 0.7em 0; color: #23395d; font-size: 1rem;">
                                <li style="margin-bottom: 0.5em;">Integrity & Trust</li>
                                <li style="margin-bottom: 0.5em;">Customer Satisfaction</li>
                                <li style="margin-bottom: 0.5em;">Personalized Solutions</li>
                                <li style="margin-bottom: 0.5em;">Expert Guidance</li>
                            </ul>
                        </div>
                    </div>
                    <p style="font-size: 1.08rem; color: #2d3a4a; margin-bottom: 0.7rem;">We are not just a financial service provider; we are your partner in achieving financial success. Join us today and take the first step towards a brighter financial future. Experience the difference with Excel Investment, where your financial success is our priority.</p>
                </div>
                <div class="about-image-modern" style="flex: 1 1 320px; min-width: 280px; display: flex; align-items: center; justify-content: center;">
                    <img src="images/image.png" alt="About Excel Investment" style="width: 100%; max-width: 400px; border-radius: 1.2rem; box-shadow: 0 2px 16px rgba(35,57,93,0.10); object-fit: cover;">
                </div>
            </div>
        </section>
        <section id="services" style=" background: linear-gradient(120deg, #e3e9f7 60%, #f5f7fa 100%);  box-shadow: 0 4px 24px rgba(35,57,93,0.08);">
            <div class="section-content">
                <h2 style="color: #23395d;">Our Services</h2>
                <div class="service-list">
                    <div class="service-item">
                        <span class="service-icon" style="display:inline-block;vertical-align:middle;margin-right:10px;">
                            <!-- Investment icon: bar chart -->
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none"><rect x="3" y="13" width="4" height="8" fill="#23395d"/><rect x="10" y="9" width="4" height="12" fill="#23395d"/><rect x="17" y="5" width="4" height="16" fill="#23395d"/></svg>
                        </span>
                        <h3 style="color:#23395d;display:inline;">Investment Plans</h3>
                        <p>Explore our diverse investment plans tailored to your financial goals. Our Services also provides
                            personalized strategies, portfolio management, and risk assessment to help our client achieve
                            greater financial goals and secure their future with tailored investment solutions and guidance.
                        </p>
                    </div>
                    <div class="service-item">
                        <span class="service-icon" style="display:inline-block;vertical-align:middle;margin-right:10px;">
                            <!-- Loan icon: money/credit card -->
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none"><rect x="2" y="7" width="20" height="10" rx="2" fill="#23395d"/><rect x="4" y="11" width="4" height="2" fill="#fff"/><circle cx="18" cy="12" r="2" fill="#fff"/></svg>
                        </span>
                        <h3 style="color:#23395d;display:inline;">Loan Services</h3>
                        <p>Access quick and easy loans with our streamlined application process, flexible repayment options,
                            and expert guidance, helping secure funding and achieve financial stability with minimal hassle.
                        </p>
                    </div>
                    <div class="service-item">
                        <span class="service-icon" style="display:inline-block;vertical-align:middle;margin-right:10px;">
                            <!-- Advisory icon: lightbulb/idea -->
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none"><path d="M12 2a7 7 0 0 1 7 7c0 2.5-1.5 4.5-3.5 5.5V17a1.5 1.5 0 0 1-3 0v-2.5C6.5 13.5 5 11.5 5 9a7 7 0 0 1 7-7z" fill="#23395d"/><rect x="10" y="19" width="4" height="2" rx="1" fill="#23395d"/></svg>
                        </span>
                        <h3 style="color:#23395d;display:inline;">Financial Advisory</h3>
                        <p>Get expert advice to make informed financial decisions. Our expert team will help you navigate
                            the complexities of investments, loans, and financial planning, ensuring you have the knowledge
                            and support to achieve your financial goals.</p>
                    </div>
                </div>
            </div>
          
               
            </div>
           
        </section>
         <section id ="investment-plans" >
            
            <div class="section-content">
                <h2 style="color: #23395d;">Investment Plans</h2>
                <p>Explore our diverse investment plans tailored to your financial goals. Our Services also provides
                    personalized strategies,portfolio management, and risk assesment to help our client achieve
                    greater financial goals and secure their future with tailored investment solutions and guidance.
                    join us today and take the first step towards a brighter financial future. Experience the difference with Excel Investment, where your financial success is our priority.

                    
                </p>
                <div class="investment-plans" >
                    <div class="plan-item" >
                        <h3 style="color:#23395d;">StarterBoost</h3>
                        <ul class="plan-features">
                            <li><span class="plan-feature-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="12" fill="#274472"/><path d="M7 13l3 3 7-7" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Low risk</li>
                            <li><span class="plan-feature-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="12" fill="#274472"/><path d="M7 13l3 3 7-7" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>5% monthly increment</li>
                            <li><span class="plan-feature-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="12" fill="#274472"/><path d="M7 13l3 3 7-7" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Minimum investment of &#8358;1000</li>
                            <li><span class="plan-feature-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="12" fill="#274472"/><path d="M7 13l3 3 7-7" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Maximum investment of &#8358;5000</li>
                            <li><span class="plan-feature-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="12" fill="#274472"/><path d="M7 13l3 3 7-7" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>24/7 customer support</li>
                        </ul>
                    </div>
                    <div class="plan-item">
                        <h3 style="color:#23395d;">ProGrow</h3>
                        <ul class="plan-features">
                            <li><span class="plan-feature-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="12" fill="#274472"/><path d="M7 13l3 3 7-7" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Moderate risk</li>
                            <li><span class="plan-feature-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="12" fill="#274472"/><path d="M7 13l3 3 7-7" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>7% monthly increment</li>
                            <li><span class="plan-feature-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="12" fill="#274472"/><path d="M7 13l3 3 7-7" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Minimum investment of &#8358;5000</li>
                            <li><span class="plan-feature-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="12" fill="#274472"/><path d="M7 13l3 3 7-7" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Maximum investment of &#8358;20000</li>
                            <li><span class="plan-feature-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="12" fill="#274472"/><path d="M7 13l3 3 7-7" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>24/7 customer support</li>
                        </ul>
                    </div>
                    <div class="plan-item">
                        <h3 style="color:#23395d;">EliteMax</h3>
                        <ul class="plan-features">
                            <li><span class="plan-feature-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="12" fill="#274472"/><path d="M7 13l3 3 7-7" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>High risk</li>
                            <li><span class="plan-feature-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="12" fill="#274472"/><path d="M7 13l3 3 7-7" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>10% monthly increment</li>
                            <li><span class="plan-feature-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="12" fill="#274472"/><path d="M7 13l3 3 7-7" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Minimum investment of &#8358;20000</li>
                            <li><span class="plan-feature-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="12" fill="#274472"/><path d="M7 13l3 3 7-7" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Maximum investment of  &#8358;100000</li>
                            <li><span class="plan-feature-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="12" fill="#274472"/><path d="M7 13l3 3 7-7" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>24/7 customer support</li>
                        </ul>
                    </div>
                  
                </div>
            </div>
            
        </div>
        </section>

    <div id="investment-calculator" class="investment-calculator" style="margin-top:2.5rem; background: linear-gradient(120deg, #e3e9f7 60%, #f5f7fa 100%); border-radius: 1.2rem; box-shadow: 0 2px 12px rgba(35,57,93,0.10); padding: 2.5rem 1.5rem;">
        <style>
        #investment-calculator {
            margin-top: 2.5rem;
            background: linear-gradient(120deg, #e3e9f7 60%, #f5f7fa 100%);
            border-radius: 1.2rem;
            box-shadow: 0 2px 18px rgba(35,57,93,0.15);
            padding: 2rem 1.2rem 1.7rem 1.2rem;
            max-width: 100%;
            margin-left: auto;
            margin-right: auto;
            border: 1.5px solid #e0e6f3;
            transition: box-shadow 0.2s, border 0.2s;
            display: flex;
            flex-direction: row;
            align-items: flex-start;
            gap: 2.2rem;
        }
        .calc-left {
            flex: 1 1 0;
            min-width: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .calc-right {
            flex: 1 1 0;
            min-width: 0;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }
        #investment-calculator:hover {
            box-shadow: 0 8px 32px rgba(35,57,93,0.22);
            border: 1.5px solid #bfc9d9;
        }
        .calc-left h2 {
            color: #23395d;
            text-align: left;
            font-size: 1.45rem;
            font-weight: 800;
            margin-bottom: 0.6rem;
            letter-spacing: 0.5px;
        }
        .calc-left p {
            text-align: left;
            color: #2d3a4a;
            font-size: 1.01rem;
            margin-bottom: 1.1rem;
        }
        #investmentCalculatorForm {
            display: flex;
            flex-direction: column;
            gap: 1.1rem;
            min-width: 200px;
            max-width: 500px;
            width: 100%;
        }
        .calc-field {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }
        .calc-field label {
            color: #23395d;
            font-weight: 700;
            font-size: 1.01rem;
            margin-bottom: 0.1rem;
        }
        .calc-field input[type="number"] {
            padding: 0.7rem 1rem;
            border-radius: 0.7rem;
            border: 1.2px solid #bfc9d9;
            font-size: 1.08rem;
            background: #f8fafc;
            transition: border-color 0.2s, box-shadow 0.2s;
            width: 100%;
            box-sizing: border-box;
        }
        .calc-field input[type="number"]:focus {
            border-color: #274472;
            box-shadow: 0 0 0 2px #e3e9f7;
            outline: none;
            background: #fff;
        }
        #investmentCalculatorForm button[type="submit"] {
            background: linear-gradient(90deg,#274472 60%,#23395d 100%);
            color: #fff;
            padding: 0.9rem 0;
            border: none;
            border-radius: 2rem;
            font-weight: 800;
            font-size: 1.08rem;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(35,57,93,0.13);
            transition: background 0.2s, box-shadow 0.2s;
            letter-spacing: 0.5px;
            margin-top: 0.1rem;
        }
        #investmentCalculatorForm button[type="submit"]:hover {
            background: linear-gradient(90deg,#23395d 60%,#274472 100%);
            box-shadow: 0 4px 18px rgba(35,57,93,0.18);
        }
        #calculationResult {
            margin-top: 1.2rem;
            text-align: left;
            font-size: 1.08rem;
            color: #23395d;
            font-weight: 600;
        }
        @media (max-width: 700px) {
            #investment-calculator {
                flex-direction: column;
                max-width: 98vw;
                padding: 1rem 0.3rem 1rem 0.3rem;
                gap: 1.2rem;
            }
            .calc-right {
                align-items: stretch;
            }
            #calculationResult {
                text-align: center;
            }
        }
        </style>
        <div class="calc-left">
            <h2>Investment Calculator</h2>
            <p>
                Estimate your potential returns based on your investment amount, duration, and interest rate.
                This calculator helps you make informed decisions by projecting your future wealth based on your chosen plan. Adjust the amount, duration, and interest rate to compare different scenarios and find the best fit for your financial goals. Whether you're planning for short-term gains or long-term growth, use this tool to visualize your investment journey and maximize your returns with confidence. Start exploring your options today!<br><br>
                                Enter your details below to see how your investment can grow over time.<br><br>
                <strong>Note:</strong> This is a simple calculator and does not account for taxes, fees, or other potential costs associated with investments.
            </p>
            <div id="calculationResult"></div>
        </div>
        <div class="calc-right">
            <form id="investmentCalculatorForm">
                <div class="calc-field">
                    <label for="amount">Investment Amount (&#8358;):</label>
                    <input type="number" id="amount" name="amount" required min="1000" step="1000">
                </div>
                <div class="calc-field">
                    <label for="duration">Duration (months):</label>
                    <input type="number" id="duration" name="duration" required min="1">
                </div>
                <div class="calc-field">
                    <label for="rate">Interest Rate (% per month):</label>
                    <input type="number" id="rate" name="rate" required min="1" step="0.1">
                </div>
                <button type="submit">Calculate</button>
            </form>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('investmentCalculatorForm');
                    if(form) {
                        form.addEventListener('submit', function(e) {
                            e.preventDefault();
                            const amount = parseFloat(document.getElementById('amount').value);
                            const duration = parseInt(document.getElementById('duration').value);
                            const rate = parseFloat(document.getElementById('rate').value);
                            if(amount > 0 && duration > 0 && rate > 0) {
                                const futureValue = amount * Math.pow(1 + (rate/100), duration);
                                document.getElementById('calculationResult').innerHTML = `After <strong>${duration}</strong> months, your investment will be worth <strong style='color:#274472;'>&#8358;${futureValue.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</strong>.`;
                            } else {
                                document.getElementById('calculationResult').innerHTML = '<span style="color:red;">Please enter valid values.</span>';
                            }
                        });
                    }
                });
                </script>
            </div>
            
        </section>
        <section id="loans">
            <div class="loan-section-modern" style="display: flex; flex-wrap: wrap; align-items: stretch; background: linear-gradient(120deg, #e3e9f7 60%, #f5f7fa 100%); border-radius: 1.5rem; box-shadow: 0 4px 24px rgba(35,57,93,0.08); margin: 2rem 0; padding: 2.5rem 1.5rem; gap: 2rem;">
                <div class="loan-image-modern" style="flex: 1 1 320px; min-width: 280px; display: flex; align-items: center; justify-content: center;">
                    <img src="images/pexels-thirdman-8470799.jpg" alt="Two people transacting" style="width: 100%; max-width: 420px; border-radius: 1.2rem; box-shadow: 0 2px 16px rgba(35,57,93,0.10); object-fit: cover;">
                </div>
                <div class="loan-content-modern" style="flex: 2 1 400px; min-width: 320px; display: flex; flex-direction: column; justify-content: center;">
                    <h2 style="color: #23395d; font-size: 2.2rem; font-weight: 700; margin-bottom: 1rem; letter-spacing: 1px;">Flexible & Fast Loan Solutions</h2>
                    <p class="loanss" style="font-size: 1.15rem; color: #2d3a4a; margin-bottom: 1.2rem;">Unlock your financial potential with our wide range of loan options designed for your needs. Whether it's personal, business, or mortgage loans, we offer competitive rates and a seamless process to help you achieve your dreams.</p>
                    <div style="display: flex; flex-wrap: wrap; gap: 1.5rem; margin-bottom: 1.2rem;">
                        <div class="loanss" style="background: #fff; border-radius: 1rem; box-shadow: 0 2px 8px rgba(35,57,93,0.07); padding: 1.2rem 1.5rem; flex: 1 1 180px; min-width: 180px;">
                            <h4 style="color: #274472; margin-bottom: 0.5rem; font-size: 1.1rem;">Loan Types</h4>
                            <ul style="list-style: none; padding: 0; margin: 0; color: #23395d;">
                                <li>Personal Loans</li>
                                <li>Business Loans</li>
                                <li>Home Mortgages</li>
                                <li>Auto Loans</li>
                                <li>Student Loans</li>
                            </ul>
                        </div>
                        <div class="loanss"  style="background: #fff; border-radius: 1rem; box-shadow: 0 2px 8px rgba(35,57,93,0.07); padding: 1.2rem 1.5rem; flex: 1 1 180px; min-width: 180px;">
                            <h4 style="color: #274472; margin-bottom: 0.5rem; font-size: 1.1rem;">Why Choose Us?</h4>
                            <ul style="list-style: none; padding: 0; margin: 0; color: #23395d;">
                                <li>Quick & easy application</li>
                                <li>Flexible repayment options</li>
                                <li>Competitive interest rates</li>
                                <li>Expert guidance & support</li>
                            </ul>
                        </div>
                    </div>
                    <div class="loanss" style="margin-top: 1rem;">
                        <p style="font-size: 1.08rem; color: #2d3a4a; margin-bottom: 0.7rem;">Our application process is simple and straightforward. Apply online or visit our office to speak with a loan officer. We'll guide you every step of the way to ensure you get the best loan for your needs.</p>
                        <a href="#contact" style="display: inline-block; background: #274472; color: #fff; padding: 0.8rem 2rem; border-radius: 2rem; font-weight: 600; font-size: 1.1rem; text-decoration: none; box-shadow: 0 2px 8px rgba(35,57,93,0.10); transition: background 0.2s;">Apply Now</a>
                    </div>
                </div>
            </div>
        </section>
        <section id="testimonials">
            <div class="section-image">
                <img id="test" src="images/co-working-people-working-together.jpg" alt="Happy Clients">
            </div>
            <div class="section-content">
                <h2 style="color: #23395d;">What Our Clients Say</h2>
                <div class="testimonial-list">
                    <div class="testimonial-item">
                        <img src="images/person-m-7.webp" alt="John Doe" class="testimonial-img">
                        <div class="testimonial-content">
                            <p>"Excel Investment has transformed my financial future. Their investment plans are top-notch! I started with little knowledge, but their team guided me every step of the way. Now, my portfolio is growing steadily and I feel secure about my future."</p>
                            <p><strong>- John Doe</strong></p>
                        </div>
                    </div>
                    <div class="testimonial-item">
                        <img src="images/person-f-5.webp" alt="Jane Smith" class="testimonial-img">
                        <div class="testimonial-content">
                            <p>"The loan services were quick and hassle-free. I highly recommend Excel Investment. Their support team was always available to answer my questions, and the process was much easier than I expected. I got the funds I needed in no time!"</p>
                            <p><strong>- Jane Smith</strong></p>
                        </div>
                    </div>
                    <div class="testimonial-item">
                        <img src="images/person-m-12.webp" alt="Mark Johnson" class="testimonial-img">
                        <div class="testimonial-content">
                            <p>"Their financial advisory team is exceptional. They helped me make informed decisions and provided personalized advice that really made a difference. I feel much more confident about my investments now."</p>
                            <p><strong>- Mark Johnson</strong></p>
                        </div>
                    </div>
                    <div class="testimonial-item">
                        <img src="images/person-f-9.webp" alt="Sarah Williams" class="testimonial-img">
                        <div class="testimonial-content">
                            <p>"Excel Investment has been a game-changer for my investments. Their expertise is unmatched! I appreciate their transparency and dedication to helping clients succeed. I recommend them to anyone serious about growing their wealth."</p>
                            <p><strong>- Sarah Williams</strong></p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
       
        
       
         </section>
        <section id="contact" style=" background: linear-gradient(120deg, #e3e9f7 60%, #f5f7fa 100%); border-radius: 1.5rem; box-shadow: 0 4px 24px rgba(35,57,93,0.08);">
            <div class="section-content">
                <h2 style="max-width: 50%;color: #23395d;">Contact Us</h2>
                <p style="max-width: 50%;">If you have any questions or need assistance, feel free to reach out to us. Our team is here to help you
                    with all your financial needs.</p>
                      <p>You can also contact us via email at <a href="mailto:austinechinasa37@gmail.com"> austinechinasa37@gmail.com</a>
                <p>or call us at <a href="tel:+1234567890">+123-456-7890</a>. We look forward to hearing from you!</p>

                    
                    <div class="contact-form">
                <?php include 'process_contact.php'; ?>
                <form action="process_contact.php" method="post">
                    <input type="text" name="name" placeholder="Your Name" required>
                    <input type="email" name="email" placeholder="Your Email" required>
                    <input type="tel" name="phone" placeholder="Your Phone Number" required>
                    <textarea rows="8" name="message" placeholder="Your Message" required></textarea>
                    <button type="submit">Send Message</button>
                </form>
            </div>
           </div>
  
        </section>
        <footer style="background-color:#232946; color:#fff;">
            <p>&copy; 2025 Excel Investment. All rights reserved.</p>
            <div class="footer-icons">
                <a href="https://www.facebook.com" target="_blank" aria-label="Facebook">
                    <svg viewBox="0 0 24 24"><path d="M22.675 0h-21.35C.595 0 0 .592 0 1.326v21.348C0 23.408.595 24 1.325 24h11.495v-9.294H9.692v-3.622h3.128V8.413c0-3.1 1.893-4.788 4.659-4.788 1.325 0 2.463.099 2.797.143v3.24l-1.918.001c-1.504 0-1.797.715-1.797 1.763v2.313h3.587l-.467 3.622h-3.12V24h6.116C23.406 24 24 23.408 24 22.674V1.326C24 .592 23.406 0 22.675 0"/></svg>
                </a>
                <a href="https://www.twitter.com" target="_blank" aria-label="Twitter">
                    <svg viewBox="0 0 24 24"><path d="M24 4.557a9.83 9.83 0 0 1-2.828.775 4.932 4.932 0 0 0 2.165-2.724c-.951.564-2.005.974-3.127 1.195a4.916 4.916 0 0 0-8.38 4.482C7.691 8.095 4.066 6.13 1.64 3.161c-.542.929-.856 2.01-.857 3.17 0 2.188 1.115 4.117 2.823 5.254a4.904 4.904 0 0 1-2.229-.616c-.054 2.281 1.581 4.415 3.949 4.89a4.936 4.936 0 0 1-2.224.084c.627 1.956 2.444 3.377 4.6 3.417A9.867 9.867 0 0 1 0 21.543a13.94 13.94 0 0 0 7.548 2.209c9.058 0 14.009-7.513 14.009-14.009 0-.213-.005-.425-.014-.636A10.012 10.012 0 0 0 24 4.557z"/></svg>
                </a>
                <a href="https://www.instagram.com" target="_blank" aria-label="Instagram">
                    <svg viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 1.366.062 2.633.334 3.608 1.308.974.974 1.246 2.241 1.308 3.608.058 1.266.069 1.646.069 4.85s-.012 3.584-.07 4.85c-.062 1.366-.334 2.633-1.308 3.608-.974.974-2.241 1.246-3.608 1.308-1.266.058-1.646.069-4.85.069s-3.584-.012-4.85-.07c-1.366-.062-2.633-.334-3.608-1.308-.974-.974-1.246-2.241-1.308-3.608C2.175 15.647 2.163 15.267 2.163 12s.012-3.584.07-4.85c.062-1.366.334-2.633 1.308-3.608.974-.974 2.241-1.246 3.608-1.308C8.416 2.175 8.796 2.163 12 2.163zm0-2.163C8.741 0 8.332.013 7.052.072 5.775.13 4.602.402 3.635 1.37 2.668 2.337 2.396 3.51 2.338 4.788 2.279 6.068 2.267 6.477 2.267 12c0 5.523.012 5.932.071 7.212.058 1.278.33 2.451 1.297 3.418.967.967 2.14 1.239 3.418 1.297C8.332 23.987 8.741 24 12 24s3.668-.013 4.948-.072c1.278-.058 2.451-.33 3.418-1.297.967-.967 1.239-2.14 1.297-3.418.059-1.28.071-1.689.071-7.212 0-5.523-.012-5.932-.071-7.212-.058-1.278-.33-2.451-1.297-3.418C19.399.402 18.226.13 16.948.072 15.668.013 15.259 0 12 0zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zm0 10.162a3.999 3.999 0 1 1 0-7.998 3.999 3.999 0 0 1 0 7.998zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881z"/></svg>
                </a>
            </div>
            <p style="margin-top:1rem;">Follow us on social media!</p>
        </footer>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>

</body>

</html></a>