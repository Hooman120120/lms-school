<?php
include_once 'dbconfig.php';
include_once 'inc/function.php';

if (isset($_GET['grade'])) {
    $current_grade = $_GET['grade'];
    $username = $_SESSION['auth']['user'] ?? '';

    // Fetch user's active cart items to detect already selected models
    $cart_items = [];
    if (!empty($username)) {
        $stmt_cart = $conn->prepare("SELECT coursecode, fee, price_type FROM checkout WHERE username = ?");
        $stmt_cart->execute([$username]);
        while ($row = $stmt_cart->fetch(PDO::FETCH_ASSOC)) {
            $cart_items[$row['coursecode']] = $row;
        }
    }

    try {
        $sql = "SELECT * FROM codtbl_course 
                WHERE paye REGEXP ? 
                AND courseactive = 1 
                AND course_type != 30 
                ORDER BY idcourse DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$current_grade]);
        $courses = $stmt->fetchAll(PDO::FETCH_OBJ);

        if ($courses) {
            echo '<div class="row row-sm p-2" style="direction: rtl; width: 100%;">';

            foreach ($courses as $fr) {
                $with_price = $fr->coursefee;
                $without_price = isset($fr->coursefee_without) && $fr->coursefee_without > 0 ? $fr->coursefee_without : 0;

                // Determine current cart state
                $in_cart = isset($cart_items[$fr->codecourse]);
                $active_model = '';

                if ($in_cart) {
                    $item = $cart_items[$fr->codecourse];
                    if (isset($item['price_type'])) {
                        $active_model = $item['price_type'];
                    } else {
                        // Fallback check by fee match if price_type column is missing
                        $active_model = ($item['fee'] == $without_price && $without_price > 0) ? 'without' : 'with';
                    }
                }
                ?>
                <div class="col-12 col-md-6 col-lg-3 mb-4">
                    <div class="course-card-vertical">
                        <div class="card-image-wrapper">
                            <div class="grade-badge">
                                <?php echo (in_array($current_grade, ['انسانی', 'تجربی', 'ریاضی', 'المپیاد'])) ? '' : t('grade') . ' '; ?>
                                <?php echo $current_grade; ?>
                            </div>
                            <img src="<?php echo $fr->piclink; ?>">
                        </div>

                        <div class="card-content">
                            <h3 class="course-title"><?php echo $fr->coursename; ?></h3>

                            <div class="price-models-container mb-3">
                                <!-- WITH SUPPORTER -->
                                <div onclick="<?php echo ($active_model === 'with') ? 'void(0)' : "selectPriceModel({$fr->codecourse}, 'with', this)"; ?>"
                                     class="price-model-card price-model-with cursor-pointer <?php echo ($active_model === 'with') ? 'selected disabled-card' : ''; ?>">
                                    <div class="price-model-header">
                                        <span class="badge bg-success">با پشتیبان</span>
                                        <?php if ($active_model === 'with') { ?>
                                            <span class="badge bg-light text-dark">انتخاب شده</span>
                                        <?php } ?>
                                    </div>
                                    <div class="price-model-body">
                                        <div class="price-value text-success">
                                            <?php echo number_format($with_price) ?> تومان
                                        </div>
                                        <small class="text-muted">دارای خدمات پشتیبانی</small>
                                    </div>
                                </div>

                                <?php if ($without_price != 0) { ?>
                                    <!-- WITHOUT SUPPORTER -->
                                    <div onclick="<?php echo ($active_model === 'without') ? 'void(0)' : "selectPriceModel({$fr->codecourse}, 'without', this)"; ?>"
                                         class="price-model-card price-model-without cursor-pointer <?php echo ($active_model === 'without') ? 'selected disabled-card' : ''; ?>">
                                        <div class="price-model-header">
                                            <span class="badge bg-primary">بدون پشتیبان</span>
                                            <?php if ($active_model === 'without') { ?>
                                                <span class="badge bg-light text-dark">انتخاب شده</span>
                                            <?php } ?>
                                        </div>
                                        <div class="price-model-body">
                                            <div class="price-value text-primary">
                                                <?php echo number_format($without_price) ?> تومان
                                            </div>
                                            <small class="text-muted">بدون پشتیبانی</small>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>

                            <button onclick="addToCart(<?php echo $fr->codecourse ?>, '<?php echo $with_price ?>', '<?php echo $without_price ?>')"
                                    id="add-btn-<?php echo $fr->codecourse ?>"
                                    class="btn-add-cart w-100" style="display: none;">
                                <i class="fa fa-shopping-basket"></i>
                                <span class="cart-text"><?php echo $in_cart ? 'تغییر و بروزرسانی' : 'افزودن به سبد'; ?></span>
                            </button>
                        </div>
                    </div>
                </div>
                <?php
            }
            echo '</div>';
        } else {
            echo "<div class='text-center w-100 p-5 text-muted'>" . m('msg.no_course_in_category') . "</div>";
        }
    } catch (PDOException $e) {
        echo "خطا: " . $e->getMessage();
    }
}
?>