<?php  ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Top-Rated Frames</title>
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
$sql = "SELECT * FROM products ORDER BY created_at DESC LIMIT 12";
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
    <img src="../assets/src/glasses-yellow.jpg" class="hero-image" alt="">
  </div>
  
  <div class="filters-section">
    <!-- Sidebar Filters -->
    <aside class="sidebar">
      <div class="filter-header">
        <h3>Filters</h3>
        <span class="hide-filters" onclick="toggleFilters()">≡ Hide Filters</span>
      </div>
      
      <button class="clear-all" onclick="clearAllFilters()">Clear all ✕</button>
      
      <div class="filter-group">
        <h4>Gender & Age</h4>
        <div class="filter-option">
          <input type="checkbox" id="male" class="filter-checkbox" data-filter="gender" value="Male">
          <label for="male">Men</label>
        </div>
        <div class="filter-option">
          <input type="checkbox" id="female" class="filter-checkbox" data-filter="gender" value="Female">
          <label for="female">Women</label>
        </div>
        <div class="filter-option">
          <input type="checkbox" id="unisex" class="filter-checkbox" data-filter="gender" value="Unisex">
          <label for="unisex">Unisex</label>
        </div>
      </div>
      
      <div class="filter-group">
        <h4>Brands</h4>
        <div class="filter-option">
          <input type="checkbox" id="rayban" class="filter-checkbox" data-filter="brand" value="Ray-Ban">
          <label for="rayban">Ray-Ban</label>
        </div>
        <div class="filter-option">
          <input type="checkbox" id="oakley" class="filter-checkbox" data-filter="brand" value="Oakley">
          <label for="oakley">Oakley</label>
        </div>
        <div class="filter-option">
          <input type="checkbox" id="coach" class="filter-checkbox" data-filter="brand" value="Coach">
          <label for="coach">Coach</label>
        </div>
      </div>
      
      <div class="filter-group">
        <h4>Lens Types</h4>
        <div class="filter-option">
          <input type="checkbox" id="single-vision" class="filter-checkbox" data-filter="lens" value="Single Vision">
          <label for="single-vision">Single Vision Lenses</label>
        </div>
        <div class="filter-option">
          <input type="checkbox" id="bifocal" class="filter-checkbox" data-filter="lens" value="Bifocal">
          <label for="bifocal">Bifocal Lenses</label>
        </div>
        <div class="filter-option">
          <input type="checkbox" id="progressive" class="filter-checkbox" data-filter="lens" value="Progressive">
          <label for="progressive">Progressive Lenses</label>
        </div>
        <div class="filter-option">
          <input type="checkbox" id="photochromic" class="filter-checkbox" data-filter="lens" value="Photochromic">
          <label for="photochromic">Photochromic Lenses</label>
        </div>
        <div class="filter-option">
          <input type="checkbox" id="bluelight" class="filter-checkbox" data-filter="lens" value="Blue Light">
          <label for="bluelight">Blue Light Filter</label>
        </div>
      </div>
      
      <div class="filter-group">
        <h4>Frame Types</h4>
        <div class="filter-option">
          <input type="checkbox" id="fullrim" class="filter-checkbox" data-filter="frame" value="Full Rim">
          <label for="fullrim">Full Rim</label>
        </div>
        <div class="filter-option">
          <input type="checkbox" id="halfrim" class="filter-checkbox" data-filter="frame" value="Half Rim">
          <label for="halfrim">Half Rim</label>
        </div>
        <div class="filter-option">
          <input type="checkbox" id="rimless" class="filter-checkbox" data-filter="frame" value="Rimless">
          <label for="rimless">Rimless</label>
        </div>
      </div>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content">
      <div class="content-header">
        <h2>Top-Rated Glasses</h2>
        <p>Find your favorites from our most popular glasses styles, including top-rated sunglasses.</p>
        
        <div class="active-filters" id="activeFilters"></div>
        
        <p class="results-count" id="resultsCount">Showing all results</p>
      </div>
      
      <!-- Product Grid -->
      <div class="product-grid" id="productGrid">
        <?php
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                echo '<div class="product-card">';
                echo '<div class="product-image">';
                if (!empty($row['image_path'])) {
                    // Convert the path: ../photo/ -> ../assets/src/ (where images actually are)
                    $imagePath = $row['image_path'];
                    
                    // If path contains ../photo/, replace it with actual location
                    if (strpos($imagePath, '../photo/') !== false) {
                        $imagePath = str_replace('../photo/', '../assets/src/', $imagePath);
                    }
                    
                    echo '<img src="' . htmlspecialchars($imagePath) . '" style="width:100%;height:100%;object-fit:cover;border-radius:6px;" alt="Product">';
                }
                echo '</div>';
                echo '<div class="product-info">';
                echo '<h3>' . htmlspecialchars($row['product_name']) . '</h3>';
                echo '<p>' . htmlspecialchars($row['brand']) . ' - ' . htmlspecialchars($row['frame_type']) . '</p>';
                echo '<p style="font-weight:600;color:#000;margin-bottom:8px;">₱' . number_format($row['price'], 2) . '</p>';
                echo '<div class="color-options">';
                echo '<div class="color-circle tortoise"></div>';
                echo '<div class="color-circle black"></div>';
                echo '</div>';
                echo '<button class="see-more-btn" onclick="openModal(' . $row['product_id'] . ')">See more</button>';
                echo '</div>';
                echo '</div>';
            }
        } else {
            echo '<p style="grid-column: 1/-1;text-align:center;padding:40px;">No products found. Please add products to the database.</p>';
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
    </div>
    
    <div class="modal-right">
      <p class="modal-category">Eyeglasses</p>
      <h2 class="modal-title" id="modalTitle">Product Name</h2>
      <p class="modal-price" id="modalPrice">₱0.00</p>
      
      <button class="add-to-cart-btn">Want Prescriptions?</button>
      
      <a href="../public/book_appointment.php" class="prescription-link">Book An Appointment Now?</a>
      
      <ul class="product-features">
        <li>Premium quality frames</li>
        <li>UV400 protection</li>
        <li>Anti-reflection coating</li>
        <li>Frame casing and cleaning cloth included</li>
      </ul>
    </div>
  </div>
</div>

<?php include '../includes/footer.php';  ?>

<script>
// Filter functionality
let activeFilters = {
    gender: [],
    brand: [],
    lens: [],
    frame: []
};

function toggleFilters() {
    const sidebar = document.querySelector('.sidebar');
    sidebar.style.display = sidebar.style.display === 'none' ? 'block' : 'none';
}

function clearAllFilters() {
    document.querySelectorAll('.filter-checkbox').forEach(checkbox => {
        checkbox.checked = false;
    });
    
    activeFilters = {
        gender: [],
        brand: [],
        lens: [],
        frame: []
    };
    
    updateActiveFiltersDisplay();
    applyFilters();
}

function updateActiveFiltersDisplay() {
    const container = document.getElementById('activeFilters');
    container.innerHTML = '';
    
    let hasFilters = false;
    
    Object.keys(activeFilters).forEach(filterType => {
        activeFilters[filterType].forEach(value => {
            hasFilters = true;
            const tag = document.createElement('span');
            tag.className = 'filter-tag';
            tag.innerHTML = `${value} <button onclick="removeFilter('${filterType}', '${value}')">✕</button>`;
            container.appendChild(tag);
        });
    });
    
    if (hasFilters) {
        const clearBtn = document.createElement('button');
        clearBtn.className = 'clear-all';
        clearBtn.textContent = 'Clear Filters';
        clearBtn.onclick = clearAllFilters;
        container.appendChild(clearBtn);
    }
}

function removeFilter(filterType, value) {
    activeFilters[filterType] = activeFilters[filterType].filter(v => v !== value);
    
    const checkbox = document.querySelector(`input[data-filter="${filterType}"][value="${value}"]`);
    if (checkbox) checkbox.checked = false;
    
    updateActiveFiltersDisplay();
    applyFilters();
}

function applyFilters() {
    const formData = new FormData();
    
    formData.append('genders', JSON.stringify(activeFilters.gender));
    formData.append('brands', JSON.stringify(activeFilters.brand));
    formData.append('lensTypes', JSON.stringify(activeFilters.lens));
    formData.append('frameTypes', JSON.stringify(activeFilters.frame));
    
    fetch('filter_products.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateProductGrid(data.products);
            document.getElementById('resultsCount').textContent = 
                `Showing ${data.count} result${data.count !== 1 ? 's' : ''}`;
        }
    })
    .catch(error => {
        console.error('Filter error:', error);
    });
}

function updateProductGrid(products) {
    const grid = document.getElementById('productGrid');
    
    if (products.length === 0) {
        grid.innerHTML = '<p style="grid-column: 1/-1;text-align:center;padding:40px;">No products match your filters.</p>';
        return;
    }
    
    grid.innerHTML = products.map(product => {
        // Fix the image path - convert ../photo/ to ../assets/src/
        let imagePath = product.image_path || '';
        if (imagePath.includes('../photo/')) {
            imagePath = imagePath.replace('../photo/', '../assets/src/');
        }
        
        return `
        <div class="product-card">
            <div class="product-image">
                ${imagePath ? `<img src="${imagePath}" style="width:100%;height:100%;object-fit:cover;border-radius:6px;" alt="${product.product_name}">` : '<div style="display:flex;align-items:center;justify-content:center;color:#999;">No Image</div>'}
            </div>
            <div class="product-info">
                <h3>${product.product_name}</h3>
                <p>${product.brand} - ${product.frame_type}</p>
                <p style="font-weight:600;color:#000;margin-bottom:8px;">₱${parseFloat(product.price).toLocaleString('en-PH', {minimumFractionDigits: 2})}</p>
                <div class="color-options">
                    <div class="color-circle tortoise"></div>
                    <div class="color-circle black"></div>
                </div>
                <button class="see-more-btn" onclick="openModal(${product.product_id})">See more</button>
            </div>
        </div>
        `;
    }).join('');
}

// Add event listeners to all filter checkboxes
document.querySelectorAll('.filter-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const filterType = this.dataset.filter;
        const value = this.value;
        
        if (this.checked) {
            if (!activeFilters[filterType].includes(value)) {
                activeFilters[filterType].push(value);
            }
        } else {
            activeFilters[filterType] = activeFilters[filterType].filter(v => v !== value);
        }
        
        updateActiveFiltersDisplay();
        applyFilters();
    });
});

// Modal functions
function openModal(productId) {
    fetch('get_product.php?id=' + productId)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert('Product not found');
                return;
            }
            
            document.getElementById('modalTitle').textContent = data.product_name;
            document.getElementById('modalPrice').textContent = '₱' + parseFloat(data.price).toLocaleString('en-PH', {minimumFractionDigits: 2});
            
            const mainImage = document.getElementById('modalMainImage');
            if (data.image_path) {
                mainImage.innerHTML = `<img src="${data.image_path}" style="width:100%;height:100%;object-fit:cover;border-radius:8px;" alt="Product">`;
            } else {
                mainImage.innerHTML = '';
            }
            
            document.getElementById('productModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        })
        .catch(error => {
            console.error('Error:', error);
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
</script>

<?php
$conn->close();
?>

</body>
</html>