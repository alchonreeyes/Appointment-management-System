<?php
// browse.php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "capstone";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 1. CAPTURE THE SEARCH TERM
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

// 2. MODIFY QUERY BASED ON SEARCH
if ($searchTerm) {
    // Secure search query using prepared statement logic later, 
    // but for now, we will filter via JS or simple SQL LIKE
    $sql = "SELECT * FROM products WHERE product_name LIKE '%$searchTerm%' OR brand LIKE '%$searchTerm%' ORDER BY created_at DESC";
    $pageTitle = "Results: '" . htmlspecialchars($searchTerm) . "'";
} else {
    $sql = "SELECT * FROM products ORDER BY created_at DESC LIMIT 12";
    $pageTitle = "TOP-RATED FRAMES";
}

$result = $conn->query($sql);
?>



<?php
// browse.php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "capstone";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch products from database
$sql = "SELECT * FROM products ORDER BY created_at DESC LIMIT 12";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Top-Rated Frames</title>
    <link rel="stylesheet" href="../assets/browse.css"> 
    <style>
        /* Fix to ensure images fit the card */
        .product-image img { width: 100%; height: 100%; object-fit: cover; border-radius: 6px; }
        .modal { z-index: 9999; }
    </style>
</head>
<body>

<?php include '../includes/navbar.php';  ?>

<div class="container">
  <div class="hero-section">
<div class="hero-content">
  <h1><?= $pageTitle ?></h1> 
  
  <?php if(!$searchTerm): ?>
      <h2>LUXE LOOKS, LOVED PRICES</h2>
      <p>5-star frames with all the style—none of the markup.</p>
      <div class="stars">★★★★★</div>
  <?php endif; ?>
</div>
    <img src="../assets/src/glasses-yellow.jpg" class="hero-image" alt="Hero" onerror="this.style.display='none'">
  </div>
     <!--centered h1 as navbar search bar content it was name of eye glasses  -->
  <h1>example text it should appear "results: rayban"</h1>
  <div class="filters-section">
    <aside class="sidebar">
      <div class="filter-header">
        <h3>Filters</h3>
        <span class="hide-filters" onclick="toggleFilters()">≡ Hide Filters</span>
      </div>
      <button class="clear-all" onclick="clearAllFilters()">Clear all ✕</button>
      
      <div class="filter-group">
        <h4>Gender & Age</h4>
        <div class="filter-option"><input type="checkbox" id="male" class="filter-checkbox" data-filter="gender" value="Male"><label for="male">Men</label></div>
        <div class="filter-option"><input type="checkbox" id="female" class="filter-checkbox" data-filter="gender" value="Female"><label for="female">Women</label></div>
        <div class="filter-option"><input type="checkbox" id="unisex" class="filter-checkbox" data-filter="gender" value="Unisex"><label for="unisex">Unisex</label></div>
      </div>
      
      <div class="filter-group">
        <h4>Brands</h4>
        <div class="filter-option"><input type="checkbox" id="rayban" class="filter-checkbox" data-filter="brand" value="Ray-Ban"><label for="rayban">Ray-Ban</label></div>
        <div class="filter-option"><input type="checkbox" id="oakley" class="filter-checkbox" data-filter="brand" value="Oakley"><label for="oakley">Oakley</label></div>
        <div class="filter-option"><input type="checkbox" id="coach" class="filter-checkbox" data-filter="brand" value="Coach"><label for="coach">Coach</label></div>
        <div class="filter-option"><input type="checkbox" id="ssss" class="filter-checkbox" data-filter="brand" value="SSSS"><label for="ssss">SSSS</label></div>
      </div>
      
      <div class="filter-group">
        <h4>Lens Types</h4>
        <div class="filter-option"><input type="checkbox" id="single-vision" class="filter-checkbox" data-filter="lens" value="Single Vision"><label for="single-vision">Single Vision</label></div>
        <div class="filter-option"><input type="checkbox" id="bifocal" class="filter-checkbox" data-filter="lens" value="Bifocal"><label for="bifocal">Bifocal</label></div>
      </div>
      
      <div class="filter-group">
        <h4>Frame Types</h4>
        <div class="filter-option"><input type="checkbox" id="fullrim" class="filter-checkbox" data-filter="frame" value="Full Rim"><label for="fullrim">Full Rim</label></div>
        <div class="filter-option"><input type="checkbox" id="halfrim" class="filter-checkbox" data-filter="frame" value="Half Rim"><label for="halfrim">Half Rim</label></div>
        <div class="filter-option"><input type="checkbox" id="rimless" class="filter-checkbox" data-filter="frame" value="Rimless"><label for="rimless">Rimless</label></div>
      </div>
    </aside>
    
    <main class="main-content">
      <button class="show-filters-btn" onclick="showFilters()">≡ Show Filters</button>
      <div class="content-header">
        <h2>Top-Rated Glasses</h2>
        <div class="active-filters" id="activeFilters"></div>
        <p class="results-count" id="resultsCount">Showing results</p>
      </div>
      
      <div class="product-grid" id="productGrid">
        <?php
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $imagePath = $row['image_path'];
                
                // Fix image path logic
                $imagePath = str_replace('../photo/', '../mod/photo/', $imagePath);
                
                echo '<div class="product-card">';
                echo '<div class="product-image">';
                if (!empty($imagePath)) {
                    echo '<img src="' . htmlspecialchars($imagePath) . '" alt="Product" onerror="this.style.display=\'none\'">';
                } else {
                    echo '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#ccc;">No Image</div>';
                }
                echo '</div>';
                echo '<div class="product-info">';
                echo '<h3>' . htmlspecialchars($row['product_name']) . '</h3>';
                echo '<p>' . htmlspecialchars($row['brand']) . ' - ' . htmlspecialchars($row['frame_type']) . '</p>';
                echo '<p class="price">₱' . number_format($row['price'], 2) . '</p>';
                echo '<button class="see-more-btn" onclick="openModal(' . $row['product_id'] . ')">See more</button>';
                echo '</div>';
                echo '</div>';
            }
        } else {
            echo '<p style="grid-column: 1/-1;text-align:center;padding:40px;">No products found.</p>';
        }
        ?>
      </div>
    </main>
  </div>
</div>

<div class="modal" id="productModal">
  <div class="modal-content">
    <button class="modal-close" onclick="closeModal()">×</button>
    <div class="modal-left">
      <div class="modal-main-image" id="modalMainImage"></div>
    </div>
    <div class="modal-right">
      <p class="modal-category">Eyeglasses</p>
      <h2 class="modal-title" id="modalTitle">Product Name</h2>
      <p class="modal-price" id="modalPrice">₱0.00</p>
      <a href="../public/appointment.php"><button class="add-to-cart-btn">Want Prescriptions?</button></a>
      <a href="../public/book_appointment.php" class="prescription-link">Book An Appointment Now?</a>
      <p id="modalDescription" style="margin-top: 20px; line-height: 1.5; color: #666;"></p>
    </div>
  </div>
</div>

<?php include '../includes/footer.php';  ?>

<script>
// 1. Initialize Filters on Load
const urlParams = new URLSearchParams(window.location.search);
const initialSearch = urlParams.get('search') || '';

// If there is a search term in URL, update the page title immediately
if (initialSearch) {
    document.querySelector('.hero-content h1').textContent = `Results: '${initialSearch}'`;
    // Hide the subtitle/stars if searching
    const sub = document.querySelector('.hero-content h2');
    if(sub) sub.style.display = 'none';
}

// 2. State Management
let activeFilters = { 
    gender: [], 
    brand: [], 
    lens: [], 
    frame: [],
    search: initialSearch // CRITICAL: Start with the URL search term
};

// 3. Setup Listeners
document.addEventListener('DOMContentLoaded', () => {
    // Check boxes if they match active filters (optional logic)
    applyFilters(); // Trigger initial load
});

document.querySelectorAll('.filter-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const type = this.dataset.filter; // e.g. "brand"
        const val = this.value;
        
        if (this.checked) {
            activeFilters[type].push(val);
        } else {
            activeFilters[type] = activeFilters[type].filter(v => v !== val);
        }
        applyFilters();
    });
});

// 4. The Filter Function
function applyFilters() {
    const formData = new FormData();
    // We send JSON strings because filter_products.php expects json_decode()
    formData.append('genders', JSON.stringify(activeFilters.gender));
    formData.append('brands', JSON.stringify(activeFilters.brand));
    formData.append('lensTypes', JSON.stringify(activeFilters.lens));
    formData.append('frameTypes', JSON.stringify(activeFilters.frame));
    
    // Send Search Term as plain text (handled by lines 75-83 in your filter_products.php)
    formData.append('search', activeFilters.search);
    
    fetch('filter_products.php', { 
        method: 'POST', 
        body: formData 
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateProductGrid(data.products);
            document.getElementById('resultsCount').textContent = `Showing ${data.count} results`;
            
            // Debugging
            if(data.count === 0 && activeFilters.search) {
                console.log("Search for " + activeFilters.search + " returned 0 results.");
            }
        } else {
            console.error("Backend Error:", data.error);
        }
    })
    .catch(error => console.error('Fetch Error:', error));
}

// 5. Render Grid
function updateProductGrid(products) {
    const grid = document.getElementById('productGrid');
    
    if (!products || products.length === 0) {
        grid.innerHTML = '<p style="grid-column: 1/-1; text-align:center; padding:40px; color:#666;">No products match your criteria.</p>';
        return;
    }
    
    grid.innerHTML = products.map(product => {
        let imagePath = product.image_path || '';
        // Path correction logic
        if (imagePath.includes('../photo/')) {
            imagePath = imagePath.replace('../photo/', '../mod/photo/');
        }
        
        return `
        <div class="product-card">
            <div class="product-image">
                ${imagePath ? `<img src="${imagePath}" alt="${product.product_name}" onerror="this.style.display='none'">` : '<div style="color:#ccc">No Image</div>'}
            </div>
            <div class="product-info">
                <h3>${product.product_name}</h3>
                <p style="text-transform: capitalize;">${product.brand} - ${product.frame_type || 'Frame'}</p>
                <p class="price">₱${parseFloat(product.price).toFixed(2)}</p>
                <button class="see-more-btn" onclick="openModal(${product.product_id})">See more</button>
            </div>
        </div>`;
    }).join('');
}

// UI Toggles
function toggleFilters() {
    document.querySelector('.sidebar').classList.toggle('hidden');
    document.querySelector('.show-filters-btn').classList.toggle('visible');
}
function showFilters() { toggleFilters(); }

function clearAllFilters() {
    document.querySelectorAll('.filter-checkbox').forEach(cb => cb.checked = false);
    // Reset but KEEP the search term if you want (or clear it too)
    // For "Clear All", usually we clear the search too:
    activeFilters = { gender: [], brand: [], lens: [], frame: [], search: '' };
    
    // Reset URL to remove ?search=...
    window.history.pushState({}, document.title, window.location.pathname);
    document.querySelector('.hero-content h1').textContent = 'TOP-RATED FRAMES';
    
    applyFilters();
}

// --- Modal Logic ---
function openModal(productId) {
    fetch('get_product.php?id=' + productId)
        .then(response => response.json())
        .then(data => {
            if (data.error) return alert('Error: ' + data.error);
            
            document.getElementById('modalTitle').textContent = data.product_name;
            document.getElementById('modalPrice').textContent = '₱' + parseFloat(data.price).toFixed(2);
            document.getElementById('modalDescription').textContent = data.description || "No description available.";
            
            const mainImage = document.getElementById('modalMainImage');
            if (data.image_path) {
                let cleanPath = data.image_path.replace('../photo/', '../mod/photo/');
                mainImage.innerHTML = `<img src="${cleanPath}" style="width:100%;height:100%;object-fit:cover;border-radius:8px;">`;
            } else {
                mainImage.innerHTML = '';
            }
            document.getElementById('productModal').classList.add('active');
        });
}

function closeModal() {
    document.getElementById('productModal').classList.remove('active');
}
</script>

</body>
</html>