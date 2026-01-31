<?php
// browse.php - Database Configuration with Environment Support

// Detect environment
$isLocal = ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === 'localhost:80' || $_SERVER['HTTP_HOST'] === '127.0.0.1');

if ($isLocal) {
    // LOCAL DEVELOPMENT
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "capstone";
} else {
    // INFINITYFREE PRODUCTION
    $servername = "sql100.infinityfree.com";
    $username = "if0_40958419";
    $password = "TQa6Uyin3H";
    $dbname = "if0_40958419_capstone";
}

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// 1. CAPTURE THE SEARCH TERM
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

// 2. FETCH DYNAMIC FILTER OPTIONS FROM DATABASE
// Get unique genders
$genderQuery = "SELECT DISTINCT gender FROM products WHERE gender IS NOT NULL AND gender != '' ORDER BY gender";
$genderResult = $conn->query($genderQuery);
$genders = [];
while($row = $genderResult->fetch_assoc()) {
    $genders[] = $row['gender'];
}

// Get unique brands
$brandQuery = "SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL AND brand != '' ORDER BY brand";
$brandResult = $conn->query($brandQuery);
$brands = [];
while($row = $brandResult->fetch_assoc()) {
    $brands[] = $row['brand'];
}

// Get unique lens types
$lensQuery = "SELECT DISTINCT lens_type FROM products WHERE lens_type IS NOT NULL AND lens_type != '' ORDER BY lens_type";
$lensResult = $conn->query($lensQuery);
$lensTypes = [];
while($row = $lensResult->fetch_assoc()) {
    $lensTypes[] = $row['lens_type'];
}

// Get unique frame types
$frameQuery = "SELECT DISTINCT frame_type FROM products WHERE frame_type IS NOT NULL AND frame_type != '' ORDER BY frame_type";
$frameResult = $conn->query($frameQuery);
$frameTypes = [];
while($row = $frameResult->fetch_assoc()) {
    $frameTypes[] = $row['frame_type'];
}

// 3. MODIFY QUERY BASED ON SEARCH
if ($searchTerm) {
    $sql = "SELECT * FROM products WHERE product_name LIKE '%$searchTerm%' OR brand LIKE '%$searchTerm%' ORDER BY created_at DESC";
    $pageTitle = "Results: '" . htmlspecialchars($searchTerm) . "'";
} else {
    $sql = "SELECT * FROM products ORDER BY created_at DESC LIMIT 12";
    $pageTitle = "TOP-RATED FRAMES";
}

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
        .product-image img { width: 100%; height: 100%; object-fit: cover; border-radius: 6px; }
        .modal { z-index: 9999; }

        /* Modal Gallery Styles */
        .modal-left {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .modal-main-display-container {
            width: 100%;
            height: 400px;
            background-color: #f0f0f0;
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e0e0e0;
        }

        #modalMainDisplayImg {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        .modal-thumbnails-row {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding-bottom: 5px;
        }

        .thumb-img {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 4px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.2s;
            opacity: 0.7;
        }

        .thumb-img:hover {
            opacity: 1;
        }

        .thumb-img.active-thumb {
            border-color: #ee4d2d;
            opacity: 1;
        }
        /* Specifications Section */
.specs-container {
    margin: 20px 0;
    padding: 20px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 12px;
    border: 1px solid #dee2e6;
}

.specs-title {
    font-size: 16px;
    font-weight: 700;
    color: #212529;
    margin-bottom: 15px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.specs-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}

.spec-item {
    display: flex;
    align-items: center;
    gap: 12px;
    background: white;
    padding: 12px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: transform 0.2s, box-shadow 0.2s;
}

.spec-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.spec-icon {
    font-size: 24px;
    flex-shrink: 0;
}

.spec-content {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.spec-label {
    font-size: 11px;
    color: #6c757d;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.spec-value {
    font-size: 14px;
    color: #212529;
    font-weight: 700;
}

/* Description Section */
.description-section {
    margin-top: 20px;
}

.description-title {
    font-size: 16px;
    font-weight: 700;
    color: #212529;
    margin-bottom: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.modal-description {
    line-height: 1.6;
    color: #495057;
    font-size: 14px;
}

/* Prescription Link Enhancement */
.prescription-link {
    display: block;
    text-align: center;
    background: linear-gradient(135deg, #2d97ee 0%, #454eff 100%);
    color: white;
    padding: 14px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    font-size: 15px;
    margin: 20px 0;
    transition: all 0.3s;
    box-shadow: 0 4px 10px rgba(238, 77, 45, 0.3);
}

.prescription-link:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(238, 77, 45, 0.4);
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .specs-grid {
        grid-template-columns: 1fr;
    }
    
    .spec-item {
        padding: 10px;
    }
    
    .spec-icon {
        font-size: 20px;
    }
}
    </style>
</head>
<body>

<?php include '../includes/navbar.php'; ?>

<div class="container">
  <div class="hero-section">
    <div class="hero-content">
      <h1><?= $pageTitle ?></h1> 
      <?php if(!$searchTerm): ?>
          <h2>LUXE LOOKS, LOVED PRICES</h2>
      <?php endif; ?>
    </div>
    <img src="../assets/src/hero-img(3).jpg" class="hero-image" alt="Hero" onerror="this.style.display='none'">
  </div>

  <div class="filters-section">
    <aside class="sidebar">
      <div class="filter-header">
        <h3>Filters</h3>
        <span class="hide-filters" onclick="toggleFilters()">≡ Hide Filters</span>
      </div>
      <button class="clear-all" onclick="clearAllFilters()">Clear all ✕</button>
      
      <!-- DYNAMIC GENDER FILTER -->
      <?php if (!empty($genders)): ?>
      <div class="filter-group">
        <h4>Gender & Age</h4>
        <?php foreach($genders as $gender): ?>
          <?php 
            $genderId = strtolower(str_replace(' ', '-', $gender));
            $displayName = $gender === 'Male' ? 'Men' : ($gender === 'Female' ? 'Women' : $gender);
          ?>
          <div class="filter-option">
            <input type="checkbox" 
                   id="<?= $genderId ?>" 
                   class="filter-checkbox" 
                   data-filter="gender" 
                   value="<?= htmlspecialchars($gender) ?>">
            <label for="<?= $genderId ?>"><?= htmlspecialchars($displayName) ?></label>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      
      <!-- DYNAMIC BRANDS FILTER -->
      <?php if (!empty($brands)): ?>
      <div class="filter-group">
        <h4>Brands</h4>
        <?php foreach($brands as $brand): ?>
          <?php $brandId = strtolower(str_replace(' ', '-', $brand)); ?>
          <div class="filter-option">
            <input type="checkbox" 
                   id="brand-<?= $brandId ?>" 
                   class="filter-checkbox" 
                   data-filter="brand" 
                   value="<?= htmlspecialchars($brand) ?>">
            <label for="brand-<?= $brandId ?>"><?= htmlspecialchars($brand) ?></label>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      
      <!-- DYNAMIC LENS TYPES FILTER -->
      <?php if (!empty($lensTypes)): ?>
      <div class="filter-group">
        <h4>Lens Types</h4>
        <?php foreach($lensTypes as $lensType): ?>
          <?php $lensId = strtolower(str_replace(' ', '-', $lensType)); ?>
          <div class="filter-option">
            <input type="checkbox" 
                   id="lens-<?= $lensId ?>" 
                   class="filter-checkbox" 
                   data-filter="lens" 
                   value="<?= htmlspecialchars($lensType) ?>">
            <label for="lens-<?= $lensId ?>"><?= htmlspecialchars($lensType) ?></label>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      
      <!-- DYNAMIC FRAME TYPES FILTER -->
      <?php if (!empty($frameTypes)): ?>
      <div class="filter-group">
        <h4>Frame Types</h4>
        <?php foreach($frameTypes as $frameType): ?>
          <?php $frameId = strtolower(str_replace(' ', '-', $frameType)); ?>
          <div class="filter-option">
            <input type="checkbox" 
                   id="frame-<?= $frameId ?>" 
                   class="filter-checkbox" 
                   data-filter="frame" 
                   value="<?= htmlspecialchars($frameType) ?>">
            <label for="frame-<?= $frameId ?>"><?= htmlspecialchars($frameType) ?></label>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
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

<!-- Modal -->
<div class="modal" id="productModal">
  <div class="modal-content">
    <button class="modal-close" onclick="closeModal()">×</button>
    <div class="modal-left">
      <div class="modal-main-display-container">
        <img id="modalMainDisplayImg" src="" alt="Product Image" onerror="this.style.display='none'">
      </div>
      <div class="modal-thumbnails-row" id="modalThumbnailsContainer" style="display: none;"></div>
    </div>
    <div class="modal-right">
      <p class="modal-category">Eyeglasses</p>
      <h2 class="modal-title" id="modalTitle">Product Name</h2>
      
      <!-- NEW: Specifications Section -->
      <div class="specs-container">
        <h3 class="specs-title">Specifications</h3>
        <div class="specs-grid">
          <div class="spec-item">
            <span class="spec-icon">👤</span>
            <div class="spec-content">
              <span class="spec-label">Gender</span>
              <span class="spec-value" id="specGender">-</span>
            </div>
          </div>
          
          <div class="spec-item">
            <span class="spec-icon">🏷️</span>
            <div class="spec-content">
              <span class="spec-label">Brand</span>
              <span class="spec-value" id="specBrand">-</span>
            </div>
          </div>
          
          <div class="spec-item">
            <span class="spec-icon">👓</span>
            <div class="spec-content">
              <span class="spec-label">Lens Type</span>
              <span class="spec-value" id="specLensType">-</span>
            </div>
          </div>
          
          <div class="spec-item">
            <span class="spec-icon">🖼️</span>
            <div class="spec-content">
              <span class="spec-label">Frame Type</span>
              <span class="spec-value" id="specFrameType">-</span>
            </div>
          </div>
        </div>
      </div>
      
      <a href="../public/appointment.php" class="prescription-link">📅 Book An Appointment Now</a>
      
      <div class="description-section">
        <h3 class="description-title">Description</h3>
        <p id="modalDescription" class="modal-description">Loading description...</p>
      </div>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
// Initialize Filters
// Initialize Filters
const urlParams = new URLSearchParams(window.location.search);
const initialSearch = urlParams.get('search') || '';

// NEW: Get filter parameters from URL
const urlBrand = urlParams.get('brand');
const urlFrameType = urlParams.get('frame_type');
const urlLensType = urlParams.get('lens_type');
const urlGender = urlParams.get('gender');

if (initialSearch) {
    document.querySelector('.hero-content h1').textContent = `Results: '${initialSearch}'`;
    const sub = document.querySelector('.hero-content h2');
    if(sub) sub.style.display = 'none';
}

// State Management with URL parameters
let activeFilters = { 
    gender: urlGender ? [urlGender] : [], 
    brand: urlBrand ? [urlBrand] : [], 
    lens: urlLensType ? [urlLensType] : [], 
    frame: urlFrameType ? [urlFrameType] : [],
    search: initialSearch 
};
// Setup Listeners
document.addEventListener('DOMContentLoaded', () => {
    // NEW: Auto-check checkboxes based on URL parameters
    if (urlBrand) {
        const brandCheckbox = document.querySelector(`input[data-filter="brand"][value="${urlBrand}"]`);
        if (brandCheckbox) {
            brandCheckbox.checked = true;
        }
    }
    
    if (urlFrameType) {
        const frameCheckbox = document.querySelector(`input[data-filter="frame"][value="${urlFrameType}"]`);
        if (frameCheckbox) {
            frameCheckbox.checked = true;
        }
    }
    
    if (urlLensType) {
        const lensCheckbox = document.querySelector(`input[data-filter="lens"][value="${urlLensType}"]`);
        if (lensCheckbox) {
            lensCheckbox.checked = true;
        }
    }
    
    if (urlGender) {
        const genderCheckbox = document.querySelector(`input[data-filter="gender"][value="${urlGender}"]`);
        if (genderCheckbox) {
            genderCheckbox.checked = true;
        }
    }
    
    // Apply filters (this will use the URL parameters we set in activeFilters)
    applyFilters(); 
});


document.querySelectorAll('.filter-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const type = this.dataset.filter;
        const val = this.value;
        
        if (this.checked) {
            activeFilters[type].push(val);
        } else {
            activeFilters[type] = activeFilters[type].filter(v => v !== val);
        }
        applyFilters();
    });
});

// Filter Function
function applyFilters() {
    const formData = new FormData();
    formData.append('genders', JSON.stringify(activeFilters.gender));
    formData.append('brands', JSON.stringify(activeFilters.brand));
    formData.append('lensTypes', JSON.stringify(activeFilters.lens));
    formData.append('frameTypes', JSON.stringify(activeFilters.frame));
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
            
            // NEW: Update active filter tags display
            updateActiveFilterTags();
            
        } else {
            console.error("Backend Error:", data.error);
            document.getElementById('resultsCount').textContent = `Error loading results.`;
        }
    })
    .catch(error => console.error('Fetch Error:', error));
}

// NEW: Function to show active filters as tags
function updateActiveFilterTags() {
    const container = document.getElementById('activeFilters');
    if (!container) return;
    
    container.innerHTML = '';
    
    const allFilters = [
        ...activeFilters.gender.map(v => ({type: 'gender', value: v})),
        ...activeFilters.brand.map(v => ({type: 'brand', value: v})),
        ...activeFilters.lens.map(v => ({type: 'lens', value: v})),
        ...activeFilters.frame.map(v => ({type: 'frame', value: v}))
    ];
    
    allFilters.forEach(filter => {
        const tag = document.createElement('div');
        tag.className = 'filter-tag';
        tag.innerHTML = `
            ${filter.value}
            <button onclick="removeFilter('${filter.type}', '${filter.value}')">×</button>
        `;
        container.appendChild(tag);
    });
}

// NEW: Function to remove individual filter
function removeFilter(type, value) {
    // Remove from active filters
    activeFilters[type] = activeFilters[type].filter(v => v !== value);
    
    // Uncheck the checkbox
    const checkbox = document.querySelector(`input[data-filter="${type}"][value="${value}"]`);
    if (checkbox) {
        checkbox.checked = false;
    }
    
    // Reapply filters
    applyFilters();
}
// Render Grid
function updateProductGrid(products) {
    const grid = document.getElementById('productGrid');
    
    if (!products || products.length === 0) {
        grid.innerHTML = '<p style="grid-column: 1/-1; text-align:center; padding:40px; color:none;">No products match your criteria.</p>';
        return;
    }
    
    grid.innerHTML = products.map(product => {
        let imagePath = product.image_path || '';
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
    activeFilters = { gender: [], brand: [], lens: [], frame: [], search: '' };
    
    // NEW: Clear URL parameters and reset page title
    window.history.pushState({}, document.title, window.location.pathname);
    document.querySelector('.hero-content h1').textContent = 'TOP-RATED FRAMES';
    const sub = document.querySelector('.hero-content h2');
    if(sub) sub.style.display = 'block';
    
    applyFilters();
}
function openModal(productId) {
    document.getElementById('modalTitle').textContent = 'Loading...';
    document.getElementById('modalMainDisplayImg').src = '';
    document.getElementById('modalThumbnailsContainer').innerHTML = '';
    document.getElementById('modalThumbnailsContainer').style.display = 'none';
    
    // Reset specs
    document.getElementById('specGender').textContent = '-';
    document.getElementById('specBrand').textContent = '-';
    document.getElementById('specLensType').textContent = '-';
    document.getElementById('specFrameType').textContent = '-';
    document.getElementById('modalDescription').textContent = 'Loading...';
    
    document.getElementById('productModal').classList.add('active');

    fetch('get_product.php?id=' + productId)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert('Error: ' + data.error);
                closeModal();
                return;
            }
            
            // Set title
            document.getElementById('modalTitle').textContent = data.product_name;
            
            // **NEW: Populate Specifications**
            document.getElementById('specGender').textContent = data.gender || 'Unisex';
            document.getElementById('specBrand').textContent = data.brand || 'N/A';
            document.getElementById('specLensType').textContent = data.lens_type || 'N/A';
            document.getElementById('specFrameType').textContent = data.frame_type || 'N/A';
            
            // Set description
            document.getElementById('modalDescription').textContent = data.description || "No description available.";
            
            const mainDisplay = document.getElementById('modalMainDisplayImg');
            const thumbsContainer = document.getElementById('modalThumbnailsContainer');
            thumbsContainer.innerHTML = ''; 

            // fetch the image path to see image on browisng product
            const cleanPath = (path) => path.replace('../photo/', '../mod/photo/');

            if (data.gallery_images && data.gallery_images.length > 0) {
                const firstImage = cleanPath(data.gallery_images[0]);
                mainDisplay.src = firstImage;
                mainDisplay.style.display = 'block';

                if (data.gallery_images.length > 1) {
                    thumbsContainer.style.display = 'flex';
                    
                    data.gallery_images.forEach((imgRawPath, index) => {
                        let imgSrc = cleanPath(imgRawPath);
                        let thumb = document.createElement('img');
                        thumb.src = imgSrc;
                        thumb.classList.add('thumb-img');
                        if (index === 0) thumb.classList.add('active-thumb');

                        thumb.onclick = function() {
                            mainDisplay.src = imgSrc;
                            document.querySelectorAll('.thumb-img').forEach(t => t.classList.remove('active-thumb'));
                            this.classList.add('active-thumb');
                        };
                        thumbsContainer.appendChild(thumb);
                    });
                }
            } else {
                 mainDisplay.style.display = 'none';
                 mainDisplay.alt = "No image available founded";
            }
        })
        .catch(err => {
            console.error(err);
            alert("Failed to fetch product details.");
            closeModal();
        });
}

function closeModal() {
    document.getElementById('productModal').classList.remove('active');
}
</script>
</body>
</html>
<?php $conn->close(); ?>