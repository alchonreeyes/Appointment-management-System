<style>
    /* General Footer Styles */
    .site-footer {
        background-color: #424242;
        color: #fff;
        padding: 40px 20px;
        font-family: 'Lucida Sans', 'Lucida Sans Regular', 'Lucida Grande', 'Lucida Sans Unicode', Geneva, Verdana, sans-serif;
    }

    .footer-container {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: 30px;
        max-width: 1200px;
        margin: 0 auto;
    }

    .footer-about, .footer-links-column {
        flex: 1;
        min-width: 220px; /* Prevents columns from getting too narrow */
    }

    .site-footer h3 {
        font-size: 1.2rem;
        font-weight: 600;
        margin-bottom: 15px;
    }

    .site-footer p {
        font-size: 0.8rem;
        line-height: 1.6;
        margin-bottom: 10px;
    }

    .site-footer ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .site-footer ul li {
        margin-bottom: 8px;
    }

    .site-footer a {
        color: #fff;
        text-decoration: none;
        font-size: 0.85rem;
        transition: color 0.3s ease;
    }

    .site-footer a:hover {
        color: #cccccc;
    }

    .footer-about .clinic-name {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 15px;
    }

    .footer-links-container {
        display: flex;
        flex-wrap: wrap;
        gap: 30px;
        flex: 2; /* Allows this section to take more space */
    }

    /* Responsive Design */

    /* For tablets */
    @media (max-width: 768px) {
        .footer-container {
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .footer-about, .footer-links-container {
            width: 100%;
            max-width: 400px; /* Limit width on centered column layout */
        }

        .footer-about .clinic-name {
            justify-content: center;
        }
    }

    /* For mobile phones */
    @media (max-width: 480px) {
        .site-footer {
            padding: 30px 15px;
        }

        .footer-links-container {
            flex-direction: column;
            align-items: center;
        }

        .footer-links-column {
            width: 100%;
        }

        .site-footer h3 {
            font-size: 1.1rem;
        }

        .site-footer p, .site-footer a {
            font-size: 0.9rem;
        }
    }
</style>

<footer class="site-footer">
    <div class="footer-container">
        
        <div class="footer-about">
            <div class="clinic-name">
                <!-- Add your logo source -->
                <img src="path/to/your/logo.png" alt="Logo" width="40" height="40">
                <h3>Eye Master Optical Clinic</h3>
            </div>
            <p><strong>Hours of Operation:</strong></p>
            <p>Monday - Friday: 8:00 AM - 5:00 PM</p>
            <p>Saturday: 9:00 AM - 6:00 PM</p>
            <p>Sunday: Closed</p>
        </div>

        <div class="footer-links-container">
            <div class="footer-links-column">
                <h3>Appointment</h3>
                <ul>
                    <li><a href="#">Eye Glasses Exam Form</a></li>
                    <li><a href="#">Medical Certificate</a></li>
                </ul>
            </div>

            <div class="footer-links-column">
                <h3>Brands</h3>
                <ul>
                    <li><a href="#">Ray-Ban</a></li>
                    <li><a href="#">Oakley</a></li>
                    <li><a href="#">Coach</a></li>
                    <li><a href="#">Armani Exchange</a></li>
                    <li><a href="#">Arnette</a></li>
                    <li><a href="#">Celine</a></li>
                    <li><a href="#">Roman King</a></li>
                </ul>
            </div>

            <div class="footer-links-column">
                <h3>&nbsp;</h3> <!-- Empty heading for alignment -->
                <ul>
                    <li><a href="#">C. Lindbergh</a></li>
                    <li><a href="#">Airflex</a></li>
                    <li><a href="#">Memoflex</a></li>
                    <li><a href="#">Kate Spade New York</a></li>
                    <li><a href="#">Herman Miller</a></li>
                    <li><a href="#">Hush Puppies</a></li>
                    <li><a href="#">Jiashie Eyes</a></li>
                </ul>
            </div>
        </div>

    </div>
</footer>