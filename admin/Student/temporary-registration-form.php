<!-- Row -->
<div class="row row-sm">
    <div class="col-lg-12">
        <div class="card custom-card">
            <div class="card-body">
                <div class="row">
                    <div class="alert alert-warning d-flex align-items-center mb-3" role="alert">
                        <i class="fe fe-alert-triangle fs-4 me-2"></i>
                        <div>
                            <strong>توجه:</strong>
                            <?= m($embedded ? 'msg.extra_program_phone_notice' : 'msg.phone_notice') ?>
                        </div>
                    </div>

                    <div class="col-9">
                        <div>
                            <div class="d-flex align-items-center mb-1" style="gap: 12px;">

                                <h6 class="main-content-label mb-0">
                                    <?php echo m('action.add_temp_student') ?>
                                </h6>

                                <span class="menu-info-trigger"
                                      data-tooltip="<?php echo m('tooltip.add_temp_student') ?>"
                                      aria-label="<?php echo m('aria.add_temp_student_help') ?>">

                                        <i class="fe fe-info fs-4"></i>
                                    </span>

                            </div>

                            <p class="text-muted card-sub-title">
                                <?php echo m('label.create_temp_account') ?>
                            </p>
                        </div>
                    </div>

                </div>
                <?php if (isset($verify) and $verify == 100) { ?>
                    <div class="alert alert-success" role="alert">
                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                        <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                type="button">
                            <span aria-hidden="true">×</span>
                        </button>
                        <?php echo m('msg.student_saved_sms') ?>
                        <hr>
                        <p class="mb-0">نام کاربری : شماره تماس ثبت شده
                        </p>
                        <p class="mb-0">رمز عبور : <?php echo 'شماره تماس ثبت شده' ?>
                        </p>
                    </div>
                <?php } ?>
                <?php if (isset($erormeli) and $erormeli == 1) { ?>
                    <div class="alert alert-danger" role="alert">
                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                        <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                type="button">
                            <span aria-hidden="true">×</span>
                        </button>
                        شماره تماس معتبر نیست

                    </div>
                <?php } ?>
                <?php if (isset($timeout) and $timeout == 1) { ?>
                    <div class="alert alert-danger alert-dismissible fade show"
                         role="alert">
                        مدت زمان انجام به پایان رسیده است
                        <button type="button" class="close" data-bs-dismiss="alert"
                                aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php } ?>
                <?php if (isset($havetel) and $havetel == 1) { ?>
                    <div class="alert alert-warning" role="alert">
                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                        <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                type="button">
                            <span aria-hidden="true">×</span>
                        </button>
                        شماره تلفن متعلق به فرد دیگری است

                    </div>
                <?php } ?>
                <?php if (isset($erortoken) and $erortoken == 1) { ?>
                    <div class="alert alert-warning" role="alert">
                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                        <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                type="button">
                            <span aria-hidden="true">×</span>
                        </button>
                        خطای غیر منتظره رخ داده است
                    </div>
                <?php } ?>
                <?php if (isset($havemeli) and $havemeli == 1) { ?>
                    <div class="alert alert-danger" role="alert">
                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                        <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                type="button">
                            <span aria-hidden="true">×</span>
                        </button>
                        این کد ملی در سیستم موجود می باشد
                    </div>
                <?php } ?>
                <section class="section">
                    <div class="row">
                        <div class="card">
                            <?php if (!isset($_SESSION[$temporarySessionKey]) and 1 == 0) { ?>
                                <form method="post" data-parsley-validate="">
                                    <?php if ($embedded): ?><input type="hidden" name="temporary_registration" value="school-extracurricular"><?php endif; ?>
                                    <div class="row">
                                        <div class="col-xl-6 mb-2">
                                            <label for="sens" class="form-label mg-t-15"><?php echo m('form.student_nid_label') ?> <span style="color: red">*</span></label>
                                            <input class="form-control" name="codemeli"
                                                   type="text"
                                                   placeholder="با اعداد انگلیسی وارد شود"
                                                   required>
                                        </div>
                                        <div class="col-xl-6">
                                            <label for="sens" class="form-label mg-t-15"><?php echo m('form.student_nationality') ?> <span style="color: red">*</span></label>
                                            <select class="form-control select2 "
                                                    name="melliat"
                                                    aria-describedby="basic-addon2" required>
                                                <option label="ملیت ">
                                                </option>
                                                <option value="01">ایرانی</option>
                                                <option value="02">اتباع</option>
                                            </select>

                                        </div>
                                    </div>
                                    <?php
                                    $hash = bin2hex(random_bytes(32));
                                    $_SESSION[$temporaryTokenKey] = $hash;
                                    ?>
                                    <input class="form-control" name="token" type="hidden"
                                           value="<?php echo $hash ?>">


                                    <div class="text-center mg-10">
                                        <button type="submit" class="btn btn-success "
                                                name="search-melli"><i
                                                    class="fe fe-arrow-left"></i>
                                            ثبت کد ملی و ادامه
                                        </button>
                                    </div>
                                </form>
                                <!--                                        end step 1-->
                            <?php } ?>
                            <?php if (!isset($_SESSION[$temporarySessionKey])) { ?>
                                <form method="post" data-parsley-validate="">
                                    <?php if ($embedded): ?><input type="hidden" name="temporary_registration" value="school-extracurricular"><?php endif; ?>
                                    <div class="col-xl-6">

                                        <label for="tel-student" class="form-label"><?php echo m('form.student_phone_short') ?>
                                            <span
                                                    style="color: red">*</span></label>
                                        <input type="number" name="tel" class="form-control"
                                               placeholder="11 رقم کامل-مثال :13***091244"
                                               pattern="^(?:9)?(0\d{10})$" required>
                                    </div>
                                    <div class="text-muted pd-t-10">

                                        <input type="checkbox" name="tel-trust">
                                        پذیرش شماره تکراری


                                    </div>
                                    <?php
                                    $hash = bin2hex(random_bytes(32));
                                    $_SESSION[$temporaryTokenKey] = $hash;
                                    ?>
                                    <input class="form-control" name="token" type="hidden"
                                           value="<?php echo $hash ?>">
                                    <div class="text-center mg-t-10 mb-3">
                                        <button type="submit" class="btn btn-success "
                                                name="search-tel">
                                            <i class="fe fe-arrow-left"></i> ثبت شماره تلفن و
                                            ادامه
                                        </button>


                                    </div>
                                </form>
                            <?php } ?>
                            <!--  End STEP 2-->
                            <!--  Start STEP 3-->
                            <?php if (isset($_SESSION[$temporarySessionKey]) and isset($_SESSION[$temporarySessionKey]['tel'])) { ?>
                                <div class="col-md-12" style="margin-top: 10px;">
                                    <div class="card custom-card ">
                                        <form action="" method="post" data-parsley-validate="">
                                            <?php
                                                $hash = bin2hex(random_bytes(32));
                                                $_SESSION[$temporaryTokenKey] = $hash;
                                            ?>
                                            <input type="hidden" name="token" value="<?= htmlspecialchars($hash, ENT_QUOTES, 'UTF-8') ?>">
                                            <?php if ($embedded): ?><input type="hidden" name="temporary_registration" value="school-extracurricular"><?php endif; ?>
                                            <div class="form-group">
                                                <div class="row row-sm">
                                                    <div class="col-lg-4 mg-t-auto">
                                                        <div class="input-group mb-3">
                                                            <div class="input-group-prepend">
                                                                <div class="input-group-text">
                                                                    نام:<span
                                                                            class="tx-danger">*</span>
                                                                </div>
                                                            </div>
                                                            <input class="form-control"
                                                                   name="fname-student"
                                                                   placeholder="نام کوچک را وارد کنید"
                                                                   required=""
                                                                   type="text"
                                                                   data-placement="bottom"
                                                                   data-bs-toggle="tooltip"
                                                                   title="فارسی بنویسید">
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-4 mg-t-auto">
                                                        <div class="input-group mb-3">
                                                            <div class="input-group-prepend">
                                                                <div class="input-group-text">
                                                                    نام خانوادگی:<span
                                                                            class="tx-danger">*</span>
                                                                </div>
                                                            </div>
                                                            <input class="form-control"
                                                                   name="lname-student"
                                                                   placeholder="نام خانوادگی را وارد کنید"
                                                                   required="" type="text"
                                                                   data-placement="bottom"
                                                                   data-bs-toggle="tooltip"
                                                                   title="فارسی بنویسید">
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-4 mg-t-auto">
                                                        <label class="form-label text-center"><?php echo m('label.student_mobile_prefilled') ?> </label>
                                                        <div class="input-group mb-3">

                                                            <input type="text"
                                                                   style="text-align: center"
                                                                   name="tel1"

                                                                   aria-describedby="basic-addon2"
                                                                   aria-label="<?php echo m('aria.student_mobile') ?>"
                                                                   class="form-control"
                                                                   value="<?php echo $_SESSION[$temporarySessionKey]['tel']; ?>"
                                                                   disabled>

                                                        </div>
                                                    </div>
                                                    <div class="col-lg-4 mg-t-20">

                                                        <div class="input-group-prepend">
                                                            <div class="input-group-text">
                                                                پایه تحصیلی: <span
                                                                        class="tx-danger">*</span>
                                                            </div>
                                                            <select class="form-control select2"
                                                                    required=""
                                                                    name="paye-student"
                                                                    aria-describedby="basic-addon2"
                                                                    id="car"
                                                                    onchange="ChangeCarList()">
<option label="<?php echo htmlspecialchars(m('form.grade_placeholder'), ENT_QUOTES, "UTF-8") ?>"></option>
<?php echo inst_level_options() ?>
</select>
                                                        </div>
                                                    </div><!-- col-4 -->
                                                    <div class="col-lg-4 mg-t-20 ">

                                                        <div class="input-group-prepend">
                                                            <div class="input-group-text">
                                                                رشته تحصیلی: <span
                                                                        class="tx-danger">*</span>
                                                            </div>
                                                            <select id="carmodel"
                                                                    class="form-control select2"
                                                                    required=""
                                                                    name="reshte-student"
                                                                    aria-describedby="basic-addon2">
                                                            </select>
                                                        </div>
                                                    </div><!-- col-4 -->
                                                    <script>
                                                        var carsAndModels = {};
                                                        <?php if (inst_is('school')): ?>
                                                        carsAndModels['1'] = ['ندارد'];
                                                        carsAndModels['2'] = ['ندارد'];
                                                        carsAndModels['3'] = ['ندارد'];
                                                        carsAndModels['4'] = ['ندارد'];
                                                        <?php endif; ?>
                                                        carsAndModels['5'] = ['ندارد'];
                                                        carsAndModels['6'] = ['ندارد'];
                                                        carsAndModels['7'] = ['ندارد'];
                                                        carsAndModels['8'] = ['ندارد'];
                                                        carsAndModels['9'] = ['ندارد'];
                                                        carsAndModels['10'] = ['ریاضی', 'تجربی', 'انسانی', 'فنی و حرفه ای'];
                                                        carsAndModels['11'] = ['ریاضی', 'تجربی', 'انسانی', 'فنی و حرفه ای'];
                                                        carsAndModels['12'] = ['ریاضی', 'تجربی', 'انسانی', 'فنی و حرفه ای'];


                                                        function ChangeCarList() {
                                                            var carList = document.getElementById("car");
                                                            var modelList = document.getElementById("carmodel");
                                                            var selCar = carList.options[carList.selectedIndex].value;
                                                            while (modelList.options.length) {
                                                                modelList.remove(0);
                                                            }
                                                            var cars = carsAndModels[selCar];
                                                            if (cars) {
                                                                var i;
                                                                for (i = 0; i < cars.length; i++) {
                                                                    var car = new Option(cars[i], i);
                                                                    modelList.options.add(car);
                                                                }
                                                            }
                                                        }
                                                    </script>
                                                    <div class="col-lg-4 mg-t-20 ">
                                                        <div class="input-group-prepend">
                                                            <div class="input-group-text">
                                                                جنسیت:
                                                                <span class="tx-danger">*</span>
                                                            </div>
                                                            <select class="form-control select2"
                                                                    required=""
                                                                    name="gender-student"
                                                                    aria-describedby="basic-addon2">
                                                                <option label="جنسیت">
                                                                </option>
                                                                <option>خانم</option>
                                                                <option>آقا</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="text-center mg-t-20">
                                                    <button class="btn ripple btn-main-primary pd-x-30 mg-l-5"
                                                            name="submit-student"
                                                            type="submit">تایید و ارسال
                                                    </button>
                                                    <button class="btn ripple btn-light pd-x-30"
                                                            type="reset">
                                                        بازنشانی
                                                    </button>

                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                    <div class="text-center mg-t-10">
                                        <a href="?<?= htmlspecialchars($temporaryChangeKey, ENT_QUOTES, 'UTF-8') ?>">
                                            <button class="btn btn-warning " name="change">
                                                تغییر شماره تماس
                                            </button>
                                        </a>
                                    </div>
                                </div>


                            <?php } ?>
                            <!--  End STEP 3-->

                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>
<!-- End Row -->
