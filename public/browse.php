<?php  ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Top-Rated Frames</title>
    <style>
        
    </style>
    <link rel="stylesheet" href="../assets/browse.css">
</head>
<body>
<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "capstone";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch products from database
$sql = "SELECT * FROM products LIMIT 12";
$result = $conn->query($sql);
?>
<?php include '../includes/navbar.php';  ?>

<div class="container">
  <!-- Hero Section -->
  <div class="hero-section">
    <div class="hero-content">
      <h1>TOP-RATED FRAMES</h1>
      <h2>LUXE LOOKS, LOVED PRICES</h2>
      <p>5-star frames with all the style—none of the markup.</p>
      <div class="stars">★★★★★</div>
    </div>
    <div class="hero-image"></div>
  </div>
  
  <div class="filters-section">
    <!-- Sidebar Filters -->
    <aside class="sidebar">
      <div class="filter-header">
        <h3>Filters</h3>
        <span class="hide-filters">≡ Hide Filters</span>
      </div>
      
      <button class="clear-all">Clear all ✕</button>
      
      <div class="filter-group">
        <h4>Gender & Age</h4>
        <div class="filter-option">
          <input type="checkbox" id="women">
          <label for="women">Women</label>
        </div>
        <div class="filter-option">
          <input type="checkbox" id="men">
          <label for="men">Men</label>
        </div>
        <div class="filter-option">
          <input type="checkbox" id="kids">
          <label for="kids">Kids</label>
        </div>
      </div>
      
      <div class="filter-group">
        <h4>Brands</h4>
        <div class="filter-option">
          <input type="checkbox" id="rayban">
          <label for="rayban">Ray-ban</label>
        </div>
        <div class="filter-option">
          <input type="checkbox" id="oakley">
          <label for="oakley">Oakley</label>
        </div>
        <div class="filter-option">
          <input type="checkbox" id="coach">
          <label for="coach">Coach</label>
        </div>
      </div>
      
      <div class="filter-group">
        <h4>Lenses</h4>
        <div class="filter-option">
          <input type="checkbox" id="apollo">
          <label for="apollo">Apollo Lenses</label>
        </div>
        <div class="filter-option">
          <input type="checkbox" id="essilor">
          <label for="essilor">Essilor Lenses</label>
        </div>
        <div class="filter-option">
          <input type="checkbox" id="hoya">
          <label for="hoya">Hoya Lenses</label>
        </div>
      </div>
      
      <div class="filter-group">
        <h4>Common types of Lenses</h4>
        <div class="filter-option">
          <input type="checkbox" id="single-vision">
          <label for="single-vision">Single Vision Lenses</label>
        </div>
        <div class="filter-option">
          <input type="checkbox" id="bifocal">
          <label for="bifocal">Bifocal Lenses</label>
        </div>
        <div class="filter-option">
          <input type="checkbox" id="progressive">
          <label for="progressive">Progressive Lenses (Multifocal)</label>
        </div>
      </div>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content">
      <div class="content-header">
        <h2>Top-Rated Glasses</h2>
        <p>Find your favorites from our most popular glasses styles, including top-rated sunglasses.</p>
        
        <div class="active-filters">
          <span class="filter-tag">Sports ✕</span>
          <button class="clear-all">Clear Filters</button>
        </div>
        
        <p class="results-count">Showing 1-24 of 57 results</p>
      </div>
      
      <!-- Product Grid -->
      <div class="product-grid">
        <?php
                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        echo '<div class="product-card">';
                        echo '<div class="product-image"></div>';
                        echo '<div class="product-info">';
                        echo '<h3>' . htmlspecialchars($row['product_name']) . '</h3>';
                        echo '<p>' . htmlspecialchars($row['frame_type']) . '</p>';
                        echo '<div class="color-options">';
                        echo '<div class="color-circle tortoise"></div>';
                        echo '<div class="color-circle black"></div>';
                        echo '</div>';
                        echo '<button class="see-more-btn" onclick="openModal(' . $row['product_id'] . ')">See more</button>';
                        echo '</div>';
                        echo '</div>';
                      }
                    } else {
                      // Show example cards if no products
                      for ($i = 1; $i <= 12; $i++) {
                        echo '<div class="product-card">';
                        echo '<div class="product-image"></div>';
                        echo '<div class="product-info">';
                        echo '<h3>Square</h3>';
                        echo '<p>Want to insert here</p>';
                        echo '<div class="color-options">';
                        echo '<div class="color-circle tortoise"></div>';
                        echo '<div class="color-circle black"></div>';
                        echo '</div>';
                        echo '<button class="see-more-btn" onclick="openModal(1)">See more</button>';
                        echo '</div>';
                        echo '</div>';
                      }
                    }
                    ?>
            </div>
          </main>
        </div>
      </div>
      
      <!-- Modal -->
      <div class="modal" id="productModal">
        <div class="modal-content">
          <button class="modal-close" onclick="closeModal()">×</button>
          
          <div class="modal-left">
            <div class="modal-main-image" id="modalMainImage"></div>
            <!-- <div class="modal-thumbnails">
              <div class="modal-thumbnail active"></div>
              <div class="modal-thumbnail"></div>
              <div class="modal-thumbnail"></div>
              <div class="modal-thumbnail"></div>
            </div> -->
          </div>
          
          <div class="modal-right">
            <p class="modal-category">Eyeglasses</p>
            <h2 class="modal-title" id="modalTitle">Classico Cc32</h2>
            
            
            <div class="modal-section">
              
              
              </div>
              
              <!-- <div class="modal-section">
                <h4>Option</h4>
                <div class="option-box selected">
                  add if you want 
                </div>
                <div class="option-box">
                  add if you want
                </div>
              </div> -->
              
              <button class="add-to-cart-btn">Want Prescriptions?</button>
              
              <a href="#" class="prescription-link">Book An Appointment Now?</a>
              
              <ul class="product-features">
                <li>Japan-design frame for everyday use</li>
                <li>Material: Metal Plastic</li>
                <li>Graded lenses with UV400 protection and Anti reflection</li>
                <li>Frame casing and Microfiber cloth included</li>
                <li>Option to upgrade to Anti-Blue Digital or Photochromic or Digi + Photo</li>
                <li>Limited edition online casing</li>
              </ul>
              
              <!-- <div class="share-section">
                <span>Share:</span>
                <div class="share-icons">
                  <div class="share-icon"></div>
                  <div class="share-icon"></div>
                  <div class="share-icon"></div>
                  <div class="share-icon"></div>
                </div> -->
              </div>
            </div>
          </div>
        </div>
        
        <?php include '../includes/footer.php';  ?>
        <script>
function openModal(productId) {
    // Fetch product details via AJAX
    fetch('get_product.php?id=' + productId)
        .then(response => response.json())
        .then(data => {
            document.getElementById('modalTitle').textContent = data.product_name;
            document.getElementById('modalPrice').textContent = '₱' + parseFloat(data.price).toLocaleString('en-PH', {minimumFractionDigits: 2});
            document.getElementById('productModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        })
        .catch(error => {
            console.error('Error:', error);
            // Show modal anyway with default values
            document.getElementById('productModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        });
}

function closeModal() {
    document.getElementById('productModal').classList.remove('active');
    document.body.style.overflow = 'auto';
}

// Close modal when clicking outside
document.getElementById('productModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// Color selection
document.querySelectorAll('.modal-color-circle').forEach(circle => {
    circle.addEventListener('click', function() {
        document.querySelectorAll('.modal-color-circle').forEach(c => c.classList.remove('selected'));
        this.classList.add('selected');
    });
});

// Option selection
document.querySelectorAll('.option-box').forEach(box => {
    box.addEventListener('click', function() {
        document.querySelectorAll('.option-box').forEach(b => b.classList.remove('selected'));
        this.classList.add('selected');
    });
});
</script>

<?php
$conn->close();
?>

</body>
</html>