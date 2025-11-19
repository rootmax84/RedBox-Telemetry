<body>
    <?php if ($isValid) { ?>
        <script src="<?php echo version_url('static/js/remote.js'); ?>"></script>
    <?php } ?>
        <div class="navbar navbar-default navbar-fixed-top navbar-inverse">
        <div class="fetch-data"></div>
        <div class="timestamp" id="timestamp">...</div>
    <?php if (!isset($_SESSION['admin']) && $limit > 0) {?>
        <div class="new-session"><a href='.' l10n='sess.new'></a></div>
    <?php } ?>
    <?php if (!$uid) { ?>
        <div class="share-img" onClick="shareRemote()" style="right:40px"></div>
    <?php } ?>
            <div class="container">
              <div id="theme-switch"></div>
    <?php if ($uid) { ?>
                <div class="login-lang" id="lang-switch" style="position:absolute;top:10px;right:40px">
                    <div class="selected-lang" id="selected-lang" style="width:24px;height:24px;color:#5d5d5d"></div>
                      <ul class="lang-options" id="lang-options" style="background:#fff">
                        <li data-value="en">English</li>
                        <li data-value="ru">Русский</li>
                        <li data-value="es">Español</li>
                        <li data-value="de">Deutsch</li>
                      </ul>
                </div>
    <?php } ?>
                <div class="navbar-header">
    <?php if (!$uid) { ?>
                 <a class="navbar-brand" href="."><div id="redhead">RedB<img src="static/img/logo.svg" alt style="height:11px;">x</div> Telemetry</a><span title="logout" class="navbar-brand logout" onClick="logout()"></span>
    <?php } else { ?>
                 <a class="navbar-brand" href="#" style="cursor:default"><div id="redhead">RedB<img src="static/img/logo.svg" alt style="height:11px;">x</div> Telemetry</a>
    <?php } ?>
                </div>
              </div>
            </div>
<?php if ($isValid) { ?>
    <div class="container-remote">
        <div class="tabs">
            <button class="tab active" data-tab="boost" l10n="rem.boost">...
                <span class="tab-text"></span>
            </button>
            <button class="tab" data-tab="protection" l10n="rem.protection">...
                <span class="tab-text"></span>
            </button>
            <button class="tab" data-tab="fan" l10n="rem.fan">...
                <span class="tab-text"></span>
            </button>
            <button class="tab" data-tab="logic" l10n="rem.logic">...
                <span class="tab-text"></span>
            </button>
            <button class="tab" data-tab="inputs" l10n="rem.inputs">...
                <span class="tab-text"></span>
            </button>
            <button class="tab" data-tab="calibration" l10n="rem.calibration">....
                <span class="tab-text"></span>
            </button>
            <button class="tab" data-tab="other" l10n="rem.other">...
                <span class="tab-text"></span>
            </button>
            <button class="tab" data-tab="config" l10n="rem.config">...
                <span class="tab-text"></span>
            </button>
        </div>

        <div id="boost" class="tab-content active">
            <!-- Контейнер 1 -->
            <form>
                <div class="card-remote">
                    <h1 l10n="boost.main.title"></h1>
                    <table class="remote-table remote-table-horizontal">
                        <tbody>
                            <tr>
                                <td l10n="dialog.maintenance.en"></td>
                                <td><label class="switch"><input type="checkbox" id="boost-status"><span class="slider round"></span></label></td>
                            </tr>
                            <tr>
                                <td class="popup-target" l10n="boost.main.target"><div class="popup popup-hidden" l10n="boost.popup.boost.pressure"></div></td>
                                <td>
                                    <div class="inc-dec" id="target-off" style="pointer-events: auto;">
                                        <div class="value-button decrease" onclick="dv('boost-target',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="boost-target" min="0" max="999" placeholder="..." onkeydown="return nolet(event);" onchange="limits_boost();" onkeyup="limits_boost();">
                                        <div class="value-button increase" onclick="iv('boost-target',1,1);limits_boost()"></div> kPa
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target" l10n="boost.main.start"><div class="popup popup-hidden" l10n="boost.popup.boost.step"></div></td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('boost-start',1,5)"></div>
                                        <input type="number" class="form-control" required="" id="boost-start" min="0" max="999" step="5" placeholder="..." onkeydown="return nolet(event);" onchange="limits_boost();" onkeyup="limits_boost();">
                                        <div class="value-button increase" onclick="iv('boost-start',1,5);limits_boost()"></div> kPa
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td l10n="boost.main.duty"></td>
                                <td>
                                    <div class="inc-dec" id="duty-off" style="pointer-events: auto;">
                                        <div class="value-button decrease" onclick="dv('boost-duty',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="boost-duty" min="0" max="100" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,100);" onchange="vlim(this.id,100);">
                                        <div class="value-button increase" onclick="iv('boost-duty',1,1);vlim('boost-duty',100)"></div> %
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target" l10n="boost.main.dc.correction"><div class="popup popup-hidden" l10n="boost.popup.dc.correction"></div></td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('boost-dc-corr',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="boost-dc-corr" min="0" max="100" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,100);" onchange="vlim(this.id,100);">
                                        <div class="value-button increase" onclick="iv('boost-dc-corr',1,1);vlim('boost-dc-corr',100)"></div> %
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target" l10n="boost.main.rpm.start"><div class="popup popup-hidden" l10n="boost.popup.rpm.start"></div></td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('boost-rpm-start',1,50)"></div>
                                        <input type="number" class="form-control" required="" id="boost-rpm-start" min="0" max="10000" step="50" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,10000);" onchange="vlim(this.id,10000);">
                                        <div class="value-button increase" onclick="iv('boost-rpm-start',1,50);vlim('boost-rpm-start',10000)"></div> rpm
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target" l10n="boost.main.rpm.end"><div class="popup popup-hidden" l10n="boost.popup.rpm.end"></div></td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('boost-rpm-end',1,50)"></div>
                                        <input type="number" class="form-control" required="" id="boost-rpm-end" min="0" max="10000" step="50" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,10000);" onchange="vlim(this.id,10000);">
                                        <div class="value-button increase" onclick="iv('boost-rpm-end',1,50);vlim('boost-rpm-end',10000)"></div> rpm
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td l10n="boost.main.rpm.duty"></td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('boost-rpm-duty',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="boost-rpm-duty" min="0" max="100" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,100);" onchange="vlim(this.id,100);">
                                        <div class="value-button increase" onclick="iv('boost-rpm-duty',1,1);vlim('boost-rpm-duty',100)"></div> %
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target" l10n="boost.main.map"><div class="popup popup-hidden" l10n="boost.popup.map"></div></td>
                                <td>
                                    <select id="boost-map" class="remote-button" onchange="filter();">
                                        <option value="" disabled="" selected="">...</option>
                                        <option value="0">OBD1</option>
                                        <option value="1">250 kPa</option>
                                        <option value="2">300 kPa</option>
                                        <option value="3">Custom</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target" l10n="boost.main.frequency"><div class="popup popup-hidden" l10n="boost.popup.frequency"></div></td>
                                <td>
                                    <select id="boost-freq" class="remote-button">
                                        <option value="" disabled="" selected="">...</option>
                                        <option value="0">30 Hz</option>
                                        <option value="1">60 Hz</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target" l10n="boost.main.digital.filter"><div class="popup popup-hidden" l10n="boost.popup.digital.filter"></div></td>
                                <td>
                                    <div class="inc-dec" id="filter-off" style="pointer-events: auto;">
                                        <div class="value-button decrease" onclick="dv('boost-map-filter',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="boost-map-filter" min="0" max="20" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,20);" onchange="vlim(this.id,20);">
                                        <div class="value-button increase" onclick="iv('boost-map-filter',1,1);vlim('boost-map-filter',20)"></div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="cntr">
                        <button id="boost-set-btn" class="btn btn-info btn-sm" l10n="btn.apply" type="button"></button>
                    </div>
            </div>
        </form>

            <!-- Контейнер 2 -->
            <form>
                <div class="card-remote">
                    <h1 l10n="boost.pid.title"></h1>
                    <table class="remote-table remote-table-horizontal">
                        <tbody>
                            <tr>
                                <td class="popup-target" l10n="boost.pid.kp"><div class="popup popup-hidden" l10n="boost.popup.kp"></div></td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('pid-kp',0,0.01)"></div>
                                        <input type="number" class="form-control" required="" id="pid-kp" min="0" max="100" step="0.01" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,100);" onchange="vlim(this.id,100);">
                                        <div class="value-button increase" onclick="iv('pid-kp',0,0.01);vlim('pid-kp',100);"></div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target" l10n="boost.pid.ki"><div class="popup popup-hidden" l10n="boost.popup.ki"></div></td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('pid-ki',0,0.01)"></div>
                                        <input type="number" class="form-control" required="" id="pid-ki" min="0" max="100" step="0.01" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,100);" onchange="vlim(this.id,100);">
                                        <div class="value-button increase" onclick="iv('pid-ki',0,0.01);vlim('pid-ki',100);"></div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target" l10n="boost.pid.kd"><div class="popup popup-hidden" l10n="boost.popup.kd"></div></td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('pid-kd',0,0.01)"></div>
                                        <input type="number" class="form-control" required="" id="pid-kd" min="0" max="100" step="0.01" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,100);" onchange="vlim(this.id,100);">
                                        <div class="value-button increase" onclick="iv('pid-kd',0,0.01);vlim('pid-kd',100);"></div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target" l10n="boost.pid.frequency"><div class="popup popup-hidden" l10n="boost.popup.pid.frequency"></div></td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('pid-freq',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="pid-freq" min="5" max="100" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,100);" onchange="vlim(this.id,100);">
                                        <div class="value-button increase" onclick="iv('pid-freq',1,1);vlim('pid-freq',100);"></div> Hz
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target" l10n="boost.pid.mode"><div class="popup popup-hidden" l10n="boost.popup.pid.mode"></div></td>
                                <td>
                                    <select id="pid-mode" class="remote-button">
                                        <option value="" disabled="" selected="">...</option>
                                        <option value="0" l10n="boost.option.mode.m"></option>
                                        <option value="1" l10n="boost.option.mode.e"></option>
                                    </select>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="cntr">
                        <button id="pid-set-btn" class="btn btn-info btn-sm" l10n="btn.apply" type="button"></button>
                    </div>
                </div>
            </form>

            <!-- Контейнер 3 -->
            <form>
                <div class="card-remote">
                    <h1 l10n="boost.gear.title"></h1>
                    <table class="remote-table remote-table-horizontal">
                        <tbody>
                            <tr>
                                <td colspan="2" l10n="dialog.maintenance.en"></td>
                                <td class="popup-target">
                                    <label class="switch"><input type="checkbox" id="boost-gear-status" onClick="checkInputs()"><span class="slider round"></span></label>
                                    <div class="popup popup-hidden" l10n="boost.popup.gear.note"></div>
                                </td>
                            </tr>
                            <tr>
                                <th l10n="boost.gear.gear"></th>
                                <th class="popup-target" l10n="boost.gear.target"><div class="popup popup-hidden" l10n="boost.popup.boost.pressure"></div></th>
                                <th l10n="boost.gear.duty"></th>
                            </tr>
                            <tr>
                                <td>1</td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('boost-g1-target',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="boost-g1-target" min="0" max="999" placeholder="..." onkeydown="return nolet(event);" onchange="limits_boost();" onkeyup="limits_boost();">
                                        <div class="value-button increase" onclick="iv('boost-g1-target',1,1);limits_boost()"></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('boost-g1-duty',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="boost-g1-duty" min="0" max="100" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,100);" onchange="vlim(this.id,100);">
                                        <div class="value-button increase" onclick="iv('boost-g1-duty',1,1);vlim('boost-g1-duty',100)"></div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>2</td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('boost-g2-target',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="boost-g2-target" min="0" max="999" placeholder="..." onkeydown="return nolet(event);" onchange="limits_boost();" onkeyup="limits_boost();">
                                        <div class="value-button increase" onclick="iv('boost-g2-target',1,1);limits_boost()"></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('boost-g2-duty',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="boost-g2-duty" min="0" max="100" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,100);" onchange="vlim(this.id,100);">
                                        <div class="value-button increase" onclick="iv('boost-g2-duty',1,1);vlim('boost-g2-duty',100)"></div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>3</td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('boost-g3-target',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="boost-g3-target" min="0" max="999" placeholder="..." onkeydown="return nolet(event);" onchange="limits_boost();" onkeyup="limits_boost();">
                                        <div class="value-button increase" onclick="iv('boost-g3-target',1,1);limits_boost()"></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('boost-g3-duty',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="boost-g3-duty" min="0" max="100" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,100);" onchange="vlim(this.id,100);">
                                        <div class="value-button increase" onclick="iv('boost-g3-duty',1,1);vlim('boost-g3-duty',100)"></div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>4</td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('boost-g4-target',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="boost-g4-target" min="0" max="999" placeholder="..." onkeydown="return nolet(event);" onchange="limits_boost();" onkeyup="limits_boost();">
                                        <div class="value-button increase" onclick="iv('boost-g4-target',1,1);limits_boost()"></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('boost-g4-duty',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="boost-g4-duty" min="0" max="100" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,100);" onchange="vlim(this.id,100);">
                                        <div class="value-button increase" onclick="iv('boost-g4-duty',1,1);vlim('boost-g4-duty',100)"></div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="cntr">
                        <button id="boost-gear-set-btn" class="btn btn-info btn-sm" l10n="btn.apply" type="button"></button>
                    </div>
                </div>
            </form>
        </div>

        <div id="protection" class="tab-content">
            <!-- Контейнер 1 -->
            <form>
                <div class="card-remote">
                    <h1 l10n="protection.title"></h1>
                    <table class="remote-table remote-table-horizontal">
                        <tbody>
                            <tr>
                                <td class="popup-target" l10n="protection.max-ect.label"><div class="popup popup-hidden" l10n="protection.max.description"></div></td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('max-ect',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="max-ect" min="0" max="125" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,125);" onchange="vlim(this.id,125);">
                                        <div class="value-button increase" onclick="iv('max-ect',1,1);vlim('max-ect',125)"></div> ℃
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target" l10n="protection.max-eot.label"><div class="popup popup-hidden" l10n="protection.max.description"></div></td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('max-eot',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="max-eot" min="0" max="125" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,125);" onchange="vlim(this.id,125);">
                                        <div class="value-button increase" onclick="iv('max-eot',1,1);vlim('max-eot',125)"></div> ℃
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target" l10n="protection.max-iat.label"><div class="popup popup-hidden" l10n="protection.max.description"></div></td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('max-iat',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="max-iat" min="0" max="125" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,125);" onchange="vlim(this.id,125);">
                                        <div class="value-button increase" onclick="iv('max-iat',1,1);vlim('max-iat',125)"></div> ℃
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target" l10n="protection.max-atf.label"><div class="popup popup-hidden" l10n="protection.max.description"></div></td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('max-atf',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="max-atf" min="0" max="125" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,125);" onchange="vlim(this.id,125);">
                                        <div class="value-button increase" onclick="iv('max-atf',1,1);vlim('max-atf',125)"></div> ℃
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target" l10n="protection.max-aat.label"><div class="popup popup-hidden" l10n="protection.max.description"></div></td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('max-aat',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="max-aat" min="0" max="125" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,125);" onchange="vlim(this.id,125);">
                                        <div class="value-button increase" onclick="iv('max-aat',1,1);vlim('max-aat',125)"></div> ℃
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target" l10n="protection.max-ext.label"><div class="popup popup-hidden" l10n="protection.max.description"></div></td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('max-ext',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="max-ext" min="0" max="125" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,125);" onchange="vlim(this.id,125);">
                                        <div class="value-button increase" onclick="iv('max-ext',1,1);vlim('max-ext',125)"></div> ℃
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target" l10n="protection.max-egt.label"><div class="popup popup-hidden" l10n="protection.max-egt.description"></div></td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('max-egt',1,5)"></div>
                                        <input type="number" class="form-control" required="" id="max-egt" min="0" max="1000" step="5" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,1000);" onchange="vlim(this.id,1000);">
                                        <div class="value-button increase" onclick="iv('max-egt',1,5);vlim('max-egt',1000)"></div> ℃
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target" l10n="protection.safe-ect.label"><div class="popup popup-hidden" l10n="protection.safe.description"></div></td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('min-ect',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="min-ect" min="0" max="100" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,100);" onchange="vlim(this.id,100);">
                                        <div class="value-button increase" onclick="iv('min-ect',1,1);vlim('min-ect',100)"></div> ℃
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target" l10n="protection.safe-eot.label"><div class="popup popup-hidden" l10n="protection.safe.description"></div></td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('min-eot',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="min-eot" min="0" max="100" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,100);" onchange="vlim(this.id,100);">
                                        <div class="value-button increase" onclick="iv('min-eot',1,1);vlim('min-eot',100)"></div> ℃
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target" l10n="protection.safe-atf.label"><div class="popup popup-hidden" l10n="protection.safe.description"></div></td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('min-atf',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="min-atf" min="0" max="100" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,100);" onchange="vlim(this.id,100);">
                                        <div class="value-button increase" onclick="iv('min-atf',1,1);vlim('min-atf',100)"></div> ℃
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target" l10n="protection.boost-limit.label"><div class="popup popup-hidden" l10n="protection.boost-limit.description"></div></td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('boost-limit',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="boost-limit" min="0" max="999" placeholder="..." onkeydown="return nolet(event);" onchange="limits_protection();" onkeyup="limits_protection();">
                                        <div class="value-button increase" onclick="iv('boost-limit',1,1);limits_protection()"></div> kPa
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target" l10n="protection.afr.label"><div class="popup popup-hidden" l10n="protection.afr.description"></div></td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('afr',0,0.1)"></div>
                                        <input type="number" class="form-control" required="" id="afr" min="0" max="22.4" step="0.1" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,22.4);" onchange="vlim(this.id,22.4);">
                                        <div class="value-button increase" onclick="iv('afr',0,0.1);vlim('afr',22.4)"></div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target" l10n="protection.low-eop.label"><div class="popup popup-hidden" l10n="protection.press.description"></div></td>
                                <td><label class="switch"><input type="checkbox" id="low-eop"><span class="slider round"></span></label></td>
                            </tr>
                            <tr>
                                <td class="popup-target" l10n="protection.low-flp.label"><div class="popup popup-hidden" l10n="protection.press.description"></div></td>
                                <td><label class="switch"><input type="checkbox" id="low-fp"><span class="slider round"></span></label></td>
                            </tr>
                            <tr>
                                <td class="popup-target" l10n="protection.knock.label"><div class="popup popup-hidden" l10n="protection.knock.description"></div></td>
                                <td><label class="switch"><input type="checkbox" id="knock"><span class="slider round"></span></label></td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="cntr">
                        <button id="p-set-btn" class="btn btn-info btn-sm" l10n="btn.apply" type="button"></button>
                    </div>
                </div>
            </form>
        </div>

        <div id="fan" class="tab-content">
            <!-- Контейнер 1 -->
            <form>
                <div class="card-remote">
                    <h1 l10n="fan.activation.title"></h1>
                    <table class="remote-table remote-table-horizontal">
                        <tbody>
                            <tr>
                                <td class="popup-target" l10n="fan.mode.label"><div class="popup popup-hidden" l10n="fan.mode.description"></div></td>
                                <td>
                                    <select id="fan-mode-sel" class="remote-button" onchange="limits_protection();">
                                        <option value="" disabled="" selected="">...</option>
                                        <option value="0">SW</option>
                                        <option value="1">PWM</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td l10n="fan.source.label"></td>
                                <td>
                                    <select id="fan-src-sel" class="remote-button">
                                        <option value="" disabled="" selected="">...</option>
                                        <option value="0" l10n="select.off"></option>
                                        <option value="1">ECT</option>
                                        <option value="2">EOT</option>
                                        <option value="3">IAT</option>
                                        <option value="4">ATF</option>
                                        <option value="5">AAT</option>
                                        <option value="6">EXT</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target" l10n="fan.target.label"><div class="popup popup-hidden" l10n="fan.target.description"></div></td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('fan-target',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="fan-target" min="0" max="125" placeholder="..." onkeydown="return nolet(event);" onchange="limits_fan();" onkeyup="limits_fan();">
                                        <div class="value-button increase" onclick="iv('fan-target',1,1);limits_fan()"></div> ℃
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target" l10n="fan.ac.label"><div class="popup popup-hidden" l10n="fan.ac.description"></div></td>
                                <td><label class="switch"><input type="checkbox" id="fan-ac"><span class="slider round"></span></label></td>
                            </tr>
                            <tr>
                                <td l10n="fan.engine.label"></td>
                                <td><label class="switch"><input type="checkbox" id="fan-engine"><span class="slider round"></span></label></td>
                            </tr>
                            <tr>
                                <td l10n="fan.test.label"></td>
                                <td><label class="switch"><input type="checkbox" id="fan-test"><span class="slider round"></span></label></td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="cntr">
                        <button id="fan-set-btn" class="btn btn-info btn-sm" l10n="btn.apply" type="button"></button>
                    </div>
                </div>
            </form>

            <!-- Контейнер 2 -->
            <form>
                <div class="card-remote">
                    <h1 l10n="fan.pwm.title"></h1>
                    <table class="remote-table remote-table-horizontal">
                        <tbody>
                            <tr>
                                <td l10n="fan.pwm.invert.label"></td>
                                <td><label class="switch"><input type="checkbox" id="pwm-invert"><span class="slider round"></span></label></td>
                            </tr>
                            <tr>
                                <td class="popup-target" l10n="fan.pwm.spd.label"><div class="popup popup-hidden" l10n="fan.pwm.spd.description"></div></td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('pwm-spd',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="pwm-spd" min="0" max="250" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,250);" onchange="vlim(this.id,250);">
                                        <div class="value-button increase" onclick="iv('pwm-spd',1,1);vlim('pwm-spd',250)"></div> km/h
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target" l10n="fan.pwm.width.label"><div class="popup popup-hidden" l10n="fan.pwm.width.description"></div></td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('pwm-width',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="pwm-width" min="3" max="20" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,20);" onchange="vlim(this.id,20);">
                                        <div class="value-button increase" onclick="iv('pwm-width',1,1);vlim('pwm-width',20)"></div> ℃
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td l10n="fan.pwm.min-dc.label"></td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('pwm-min-dc',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="pwm-min-dc" min="10" max="30" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,30);" onchange="vlim(this.id,30);">
                                        <div class="value-button increase" onclick="iv('pwm-min-dc',1,1);vlim('pwm-min-dc',30)"></div> %
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target" l10n="fan.pwm.freq.label"><div class="popup popup-hidden" l10n="fan.pwm.freq.description"></div></td>
                                <td>
                                    <select id="pwm-freq" class="remote-button">
                                        <option value="" disabled="" selected="">...</option>
                                        <option value="0">490 Hz</option>
                                        <option value="1">120 Hz</option>
                                        <option value="2">30 Hz</option>
                                    </select>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="cntr">
                        <button id="pwm-set-btn" class="btn btn-info btn-sm" l10n="btn.apply" type="button"></button>
                    </div>
                </div>
            </form>

            <!-- Контейнер 3 -->
            <form>
                <div class="card-remote">
                    <h1 l10n="fan.sw.title"></h1>
                    <table class="remote-table remote-table-horizontal">
                        <tbody>
                            <tr>
                                <td l10n="fan.sw.hyst.label"></td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('hyst',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="hyst" min="1" max="10" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,10);" onchange="vlim(this.id,10);">
                                        <div class="value-button increase" onclick="iv('hyst',1,1);vlim('hyst',10)"></div> ℃
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target" l10n="fan.sw.off-delay.label"><div class="popup popup-hidden" l10n="fan.sw.off-delay.description"></div></td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('sw-off-delay',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="sw-off-delay" min="0" max="65" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,65);" onchange="vlim(this.id,65);">
                                        <div class="value-button increase" onclick="iv('sw-off-delay',1,1);vlim('sw-off-delay',65)"></div> sec
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="cntr">
                        <button id="sw-set-btn" class="btn btn-info btn-sm" l10n="btn.apply" type="button"></button>
                    </div>
                </div>
            </form>
        </div>

        <div id="logic" class="tab-content">
            <!-- Контейнер 1 -->
            <form>
                <div class="card-remote">
                    <h1 l10n="logic.pg0.title"></h1>
                    <table class="remote-table remote-table-horizontal">
                        <tbody>
                            <tr>
                                <td l10n="dialog.maintenance.en"></td>
                                <td colspan="3"><label class="switch"><input type="checkbox" id="pg0-status"><span class="slider round"></span></label></td>
                            </tr>
                            <tr>
                                <td class="popup-target"><span l10n="logic.engine-run.label"></span><div class="popup popup-hidden" l10n="logic.engine-run.description"></div></td>
                                <td colspan="3"><label class="switch"><input type="checkbox" id="pg0-engine-run"><span class="slider round"></span></label></td>
                            </tr>
                            <tr>
                                <th></th>
                                <th><span l10n="logic.var.label"></span></th>
                                <th><span l10n="logic.operator.label"></span></th>
                                <th><span l10n="logic.value.label"></span></th>
                            </tr>
                            <tr>
                                <td><span l10n="logic.condition-a.label"></span></td>
                                <td>
                                    <select id="pg0-a-var" class="remote-button wh st" onchange="document.getElementById('pg0-a-value').value='0';limits_logic();">
                                        <option value="" disabled="" selected="">...</option>
                                        <option value="0" l10n="select.off"></option>
                                        <option value="1">ECT</option>
                                        <option value="2">EOT</option>
                                        <option value="3">IAT</option>
                                        <option value="4">ATF</option>
                                        <option value="5">AAT</option>
                                        <option value="6">EXT</option>
                                        <option value="7">TPS</option>
                                        <option value="8">SPD</option>
                                        <option value="9">RLC</option>
                                        <option value="10">BST</option>
                                        <option value="11">EOP</option>
                                        <option value="12">FLP</option>
                                        <option value="13">MAP</option>
                                        <option value="14">RPM</option>
                                        <option value="15">VLT</option>
                                        <option value="16">EGT</option>
                                        <option value="17">KNK</option>
                                        <option value="18">AFR</option>
                                        <option value="19">ERT</option>
                                        <option value="20">MH</option>
                                        <option value="21">BS1</option>
                                        <option value="22">BS2</option>
                                    </select>
                                </td>
                                <td>
                                    <select id="pg0-a-operand" class="remote-button">
                                        <option value="" disabled="" selected="">...</option>
                                        <option value="1">&gt;</option>
                                        <option value="0">&lt;</option>
                                    </select>
                                </td>
                                <td>
                                    <div class="inc-dec popup-target">
                                        <input type="number" class="form-control" required="" id="pg0-a-value" min="0" max="65535" placeholder="..." onkeydown="return nolet(event);" onchange="limits_logic();" onkeyup="limits_logic();" step="1">
                                        <div class="popup popup-hidden" id="pg0-a-label" hidden=""></div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td><span l10n="logic.condition-b.label"></span></td>
                                <td>
                                    <select id="pg0-b-var" class="remote-button wh st" onchange="document.getElementById('pg0-b-value').value='0';limits_logic();">
                                        <option value="" disabled="" selected="">...</option>
                                        <option value="0" l10n="select.off"></option>
                                        <option value="1">ECT</option>
                                        <option value="2">EOT</option>
                                        <option value="3">IAT</option>
                                        <option value="4">ATF</option>
                                        <option value="5">AAT</option>
                                        <option value="6">EXT</option>
                                        <option value="7">TPS</option>
                                        <option value="8">SPD</option>
                                        <option value="9">RLC</option>
                                        <option value="10">BST</option>
                                        <option value="11">EOP</option>
                                        <option value="12">FLP</option>
                                        <option value="13">MAP</option>
                                        <option value="14">RPM</option>
                                        <option value="15">VLT</option>
                                        <option value="16">EGT</option>
                                        <option value="17">KNK</option>
                                        <option value="18">AFR</option>
                                        <option value="19">ERT</option>
                                        <option value="20">MH</option>
                                        <option value="21">BS1</option>
                                        <option value="22">BS2</option>
                                    </select>
                                </td>
                                <td>
                                    <select id="pg0-b-operand" class="remote-button">
                                        <option value="" disabled="" selected="">...</option>
                                        <option value="1">&gt;</option>
                                        <option value="0">&lt;</option>
                                    </select>
                                </td>
                                <td>
                                    <div class="inc-dec popup-target">
                                        <input type="number" class="form-control" required="" id="pg0-b-value" min="0" max="65535" placeholder="..." onkeydown="return nolet(event);" onchange="limits_logic();" onkeyup="limits_logic();" step="1">
                                        <div class="popup popup-hidden" id="pg0-b-label" hidden=""></div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td><span l10n="logic.function.label"></span></td>
                                <td colspan="3">
                                    <select id="pg0-func" class="remote-button">
                                        <option value="" disabled="" selected="">...</option>
                                        <option value="0">A</option>
                                        <option value="1" l10n="logic.anb"></option>
                                        <option value="2" l10n="logic.aorb"></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td><span l10n="logic.turn-off-delay.label"></span></td>
                                <td colspan="3">
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('pg0-off-delay',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="pg0-off-delay" min="0" max="65" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,65);" onchange="vlim(this.id,65);">
                                        <div class="value-button increase" onclick="iv('pg0-off-delay',1,1);vlim('pg0-off-delay',65)"></div> sec
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td><span l10n="logic.turn-on-delay.label"></span></td>
                                <td colspan="3">
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('pg0-on-delay',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="pg0-on-delay" min="0" max="10" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,10);" onchange="vlim(this.id,10);">
                                        <div class="value-button increase" onclick="iv('pg0-on-delay',1,1);vlim('pg0-on-delay',10)"></div> sec
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target"><span l10n="logic.on-limit.label"></span><div class="popup popup-hidden" l10n="logic.on-limit.description"></div></td>
                                <td colspan="3">
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('pg0-on-limit',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="pg0-on-limit" min="0" max="10" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,10);" onchange="vlim(this.id,10);">
                                        <div class="value-button increase" onclick="iv('pg0-on-limit',1,1);vlim('pg0-on-limit',10)"></div> sec
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target"><span l10n="logic.loop.label"></span><div class="popup popup-hidden" l10n="logic.loop.description"></div></td>
                                <td colspan="3"><label class="switch"><input type="checkbox" id="pg0-loop"><span class="slider round"></span></label></td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="cntr">
                        <button id="pg0-set-btn" class="btn btn-info btn-sm" l10n="btn.apply" type="button"></button>
                    </div>
                </div>
            </form>

            <!-- Контейнер 2 -->
            <form>
                <div class="card-remote">
                    <h1 l10n="logic.pg1.title"></h1>
                    <table class="remote-table remote-table-horizontal">
                        <tbody>
                            <tr>
                                <td l10n="dialog.maintenance.en"></td>
                                <td colspan="3"><label class="switch"><input type="checkbox" id="pg1-status"><span class="slider round"></span></label></td>
                            </tr>
                            <tr>
                                <td class="popup-target"><span l10n="logic.engine-run.label"></span><div class="popup popup-hidden" l10n="logic.engine-run.description"></div></td>
                                <td colspan="3"><label class="switch"><input type="checkbox" id="pg1-engine-run"><span class="slider round"></span></label></td>
                            </tr>
                            <tr>
                                <th></th>
                                <th><span l10n="logic.var.label"></span></th>
                                <th><span l10n="logic.operator.label"></span></th>
                                <th><span l10n="logic.value.label"></span></th>
                            </tr>
                            <tr>
                                <td><span l10n="logic.condition-a.label"></span></td>
                                <td>
                                    <select id="pg1-a-var" class="remote-button wh st" onchange="document.getElementById('pg1-a-value').value='0';limits_logic();">
                                        <option value="" disabled="" selected="">...</option>
                                        <option value="0" l10n="select.off"></option>
                                        <option value="1">ECT</option>
                                        <option value="2">EOT</option>
                                        <option value="3">IAT</option>
                                        <option value="4">ATF</option>
                                        <option value="5">AAT</option>
                                        <option value="6">EXT</option>
                                        <option value="7">TPS</option>
                                        <option value="8">SPD</option>
                                        <option value="9">RLC</option>
                                        <option value="10">BST</option>
                                        <option value="11">EOP</option>
                                        <option value="12">FLP</option>
                                        <option value="13">MAP</option>
                                        <option value="14">RPM</option>
                                        <option value="15">VLT</option>
                                        <option value="16">EGT</option>
                                        <option value="17">KNK</option>
                                        <option value="18">AFR</option>
                                        <option value="19">ERT</option>
                                        <option value="20">MH</option>
                                        <option value="21">BS1</option>
                                        <option value="22">BS2</option>
                                    </select>
                                </td>
                                <td>
                                    <select id="pg1-a-operand" class="remote-button">
                                        <option value="" disabled="" selected="">...</option>
                                        <option value="1">&gt;</option>
                                        <option value="0">&lt;</option>
                                    </select>
                                </td>
                                <td>
                                    <div class="inc-dec">
                                        <input type="number" class="form-control" required="" id="pg1-a-value" min="0" max="65535" placeholder="..." onkeydown="return nolet(event);" onchange="limits_logic();" onkeyup="limits_logic();" step="1">
                                        <div class="popup popup-hidden" id="pg1-a-label" hidden=""></div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td><span l10n="logic.condition-b.label"></span></td>
                                <td>
                                    <select id="pg1-b-var" class="remote-button wh st" onchange="document.getElementById('pg1-b-value').value='0';limits_logic();">
                                        <option value="" disabled="" selected="">...</option>
                                        <option value="0" l10n="select.off"></option>
                                        <option value="1">ECT</option>
                                        <option value="2">EOT</option>
                                        <option value="3">IAT</option>
                                        <option value="4">ATF</option>
                                        <option value="5">AAT</option>
                                        <option value="6">EXT</option>
                                        <option value="7">TPS</option>
                                        <option value="8">SPD</option>
                                        <option value="9">RLC</option>
                                        <option value="10">BST</option>
                                        <option value="11">EOP</option>
                                        <option value="12">FLP</option>
                                        <option value="13">MAP</option>
                                        <option value="14">RPM</option>
                                        <option value="15">VLT</option>
                                        <option value="16">EGT</option>
                                        <option value="17">KNK</option>
                                        <option value="18">AFR</option>
                                        <option value="19">ERT</option>
                                        <option value="20">MH</option>
                                        <option value="21">BS1</option>
                                        <option value="22">BS2</option>
                                    </select>
                                </td>
                                <td>
                                    <select id="pg1-b-operand" class="remote-button">
                                        <option value="" disabled="" selected="">...</option>
                                        <option value="1">&gt;</option>
                                        <option value="0">&lt;</option>
                                    </select>
                                </td>
                                <td>
                                    <div class="inc-dec popup-target">
                                        <input type="number" class="form-control" required="" id="pg1-b-value" min="0" max="65535" placeholder="..." onkeydown="return nolet(event);" onchange="limits_logic();" onkeyup="limits_logic();" step="1">
                                        <div class="popup popup-hidden" id="pg1-b-label" hidden=""></div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td><span l10n="logic.function.label"></span></td>
                                <td colspan="3">
                                    <select id="pg1-func" class="remote-button">
                                        <option value="" disabled="" selected="">...</option>
                                        <option value="0">A</option>
                                        <option value="1" l10n="logic.anb"></option>
                                        <option value="2" l10n="logic.aorb"></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td><span l10n="logic.turn-off-delay.label"></span></td>
                                <td colspan="3">
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('pg1-off-delay',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="pg1-off-delay" min="0" max="65" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,65);" onchange="vlim(this.id,65);">
                                        <div class="value-button increase" onclick="iv('pg1-off-delay',1,1);vlim('pg1-off-delay',65)"></div> sec
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td><span l10n="logic.turn-on-delay.label"></span></td>
                                <td colspan="3">
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('pg1-on-delay',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="pg1-on-delay" min="0" max="10" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,10);" onchange="vlim(this.id,10);">
                                        <div class="value-button increase" onclick="iv('pg1-on-delay',1,1);vlim('pg1-on-delay',10)"></div> sec
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target"><span l10n="logic.on-limit.label"></span><div class="popup popup-hidden" l10n="logic.on-limit.description"></div></td>
                                <td colspan="3">
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('pg1-on-limit',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="pg1-on-limit" min="0" max="10" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,10);" onchange="vlim(this.id,10);">
                                        <div class="value-button increase" onclick="iv('pg1-on-limit',1,1);vlim('pg1-on-limit',10)"></div> sec
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target"><span l10n="logic.loop.label"></span><div class="popup popup-hidden" l10n="logic.loop.description"></div></td>
                                <td colspan="3"><label class="switch"><input type="checkbox" id="pg1-loop"><span class="slider round"></span></label></td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="cntr">
                        <button id="pg1-set-btn" class="btn btn-info btn-sm" l10n="btn.apply" type="button"></button>
                    </div>
                </div>
            </form>

            <!-- logic.vars -->
            <div class="card-remote">
                <h1 l10n="logic.variables.title"></h1>
                <table class="remote-table remote-table-horizontal">
                    <tbody>
                        <tr>
                            <td class="bld">ECT</td>
                            <td l10n="logic.var.ect.description"></td>
                        </tr>
                        <tr>
                            <td class="bld">EOT</td>
                            <td l10n="logic.var.eot.description"></td>
                        </tr>
                        <tr>
                            <td class="bld">IAT</td>
                            <td l10n="logic.var.iat.description"></td>
                        </tr>
                        <tr>
                            <td class="bld">ATF</td>
                            <td l10n="logic.var.atf.description"></td>
                        </tr>
                        <tr>
                            <td class="bld">AAT</td>
                            <td l10n="logic.var.aat.description"></td>
                        </tr>
                        <tr>
                            <td class="bld">EXT</td>
                            <td l10n="logic.var.ext.description"></td>
                        </tr>
                        <tr>
                            <td class="bld">TPS</td>
                            <td l10n="logic.var.tps.description"></td>
                        </tr>
                        <tr>
                            <td class="bld">SPD</td>
                            <td l10n="logic.var.spd.description"></td>
                        </tr>
                        <tr>
                            <td class="bld">RLC</td>
                            <td l10n="logic.var.rlc.description"></td>
                        </tr>
                        <tr>
                            <td class="bld">BST</td>
                            <td l10n="logic.var.bst.description"></td>
                        </tr>
                        <tr>
                            <td class="bld">EOP</td>
                            <td l10n="logic.var.eop.description"></td>
                        </tr>
                        <tr>
                            <td class="bld">FLP</td>
                            <td l10n="logic.var.flp.description"></td>
                        </tr>
                        <tr>
                            <td class="bld">MAP</td>
                            <td l10n="logic.var.map.description"></td>
                        </tr>
                        <tr>
                            <td class="bld">RPM</td>
                            <td l10n="logic.var.rpm.description"></td>
                        </tr>
                        <tr>
                            <td class="bld">VLT</td>
                            <td l10n="logic.var.vlt.description"></td>
                        </tr>
                        <tr>
                            <td class="bld">EGT</td>
                            <td l10n="logic.var.egt.description"></td>
                        </tr>
                        <tr>
                            <td class="bld">KNK</td>
                            <td l10n="logic.var.knk.description"></td>
                        </tr>
                        <tr>
                            <td class="bld">AFR</td>
                            <td l10n="logic.var.afr.description"></td>
                        </tr>
                        <tr>
                            <td class="bld">ERT</td>
                            <td l10n="logic.var.ert.description"></td>
                        </tr>
                        <tr>
                            <td class="bld">MH</td>
                            <td l10n="logic.var.mh.description"></td>
                        </tr>
                        <tr>
                            <td class="bld">BS1</td>
                            <td l10n="logic.var.bs1.description"></td>
                        </tr>
                        <tr>
                            <td class="bld">BS2</td>
                            <td l10n="logic.var.bs2.description"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="inputs" class="tab-content">
            <!-- Контейнер 1 -->
            <div class="card-remote">
                    <h1 l10n="inputs.aux.inputs.title"></h1>
                    <table class="remote-table remote-table-horizontal">
                        <tbody>
                            <tr>
                                <td>AUX0</td>
                                <td>
                                    <select id="aux0-sel" class="remote-button">
                                        <option value="" disabled="" selected="">...</option>
                                        <option value="0" l10n="select.off"></option>
                                        <option value="1">ECT</option>
                                        <option value="2">EOT</option>
                                        <option value="3">IAT</option>
                                        <option value="4">ATF</option>
                                        <option value="5">AAT</option>
                                        <option value="6">EXT</option>
                                        <option value="7">MAP</option>
                                        <option value="8">FLP</option>
                                        <option value="9">AFR</option>
                                        <option value="10">EOP</option>
                                    </select>
                                </td>
                                <td class="popup-target">
                                    <label class="switch"><input type="checkbox" id="a0-pullup"><span class="slider round"></span></label>
                                    <div class="popup popup-hidden" l10n="inputs.aux.pullup.description"></div>
                                </td>
                                <td l10n="inputs.aux.pullup"></td>
                            </tr>
                            <tr>
                                <td>AUX1</td>
                                <td>
                                    <select id="aux1-sel" class="remote-button">
                                        <option value="" disabled="" selected="">...</option>
                                        <option value="0" l10n="select.off"></option>
                                        <option value="1">ECT</option>
                                        <option value="2">EOT</option>
                                        <option value="3">IAT</option>
                                        <option value="4">ATF</option>
                                        <option value="5">AAT</option>
                                        <option value="6">EXT</option>
                                        <option value="7">MAP</option>
                                        <option value="8">FLP</option>
                                        <option value="9">AFR</option>
                                        <option value="10">EOP</option>
                                    </select>
                                </td>
                                <td class="popup-target">
                                    <label class="switch"><input type="checkbox" id="a1-pullup"><span class="slider round"></span></label>
                                    <div class="popup popup-hidden" l10n="inputs.aux.pullup.description"></div>
                                </td>
                                <td l10n="inputs.aux.pullup"></td>
                            </tr>
                            <tr>
                                <td>AUX2</td>
                                <td>
                                    <select id="aux2-sel" class="remote-button">
                                        <option value="" disabled="" selected="">...</option>
                                        <option value="0" l10n="select.off"></option>
                                        <option value="1">ECT</option>
                                        <option value="2">EOT</option>
                                        <option value="3">IAT</option>
                                        <option value="4">ATF</option>
                                        <option value="5">AAT</option>
                                        <option value="6">EXT</option>
                                        <option value="7">MAP</option>
                                        <option value="8">FLP</option>
                                        <option value="9">AFR</option>
                                        <option value="10">EOP</option>
                                    </select>
                                </td>
                                <td></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td>AUX3</td>
                                <td>
                                    <select id="aux3-sel" class="remote-button">
                                        <option value="" disabled="" selected="">...</option>
                                        <option value="0" l10n="select.off"></option>
                                        <option value="1">ECT</option>
                                        <option value="2">EOT</option>
                                        <option value="3">IAT</option>
                                        <option value="4">ATF</option>
                                        <option value="5">AAT</option>
                                        <option value="6">EXT</option>
                                        <option value="7">MAP</option>
                                        <option value="8">FLP</option>
                                        <option value="9">AFR</option>
                                        <option value="10">EOP</option>
                                    </select>
                                </td>
                                <td></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td>AUX4</td>
                                <td>
                                    <select id="aux4-sel" class="remote-button">
                                        <option value="" disabled="" selected="">...</option>
                                        <option value="0" l10n="select.off"></option>
                                        <option value="1">ECT</option>
                                        <option value="2">EOT</option>
                                        <option value="3">IAT</option>
                                        <option value="4">ATF</option>
                                        <option value="5">AAT</option>
                                        <option value="6">EXT</option>
                                        <option value="7">MAP</option>
                                        <option value="8">FLP</option>
                                        <option value="9">AFR</option>
                                        <option value="10">EOP</option>
                                    </select>
                                </td>
                                <td></td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="cntr">
                        <button id="aux-set-btn" class="btn btn-info btn-sm" l10n="btn.apply"></button>
                    </div>
            </div>

            <!-- Контейнер 2 -->
            <div class="card-remote">
                    <h1 l10n="inputs.dsx.input.title"></h1>
                    <table class="remote-table remote-table-horizontal">
                        <tbody>
                            <tr>
                                <th l10n="inputs.dsx.sensor"></th>
                                <th l10n="inputs.dsx.bind"></th>
                                <th l10n="inputs.dsx.status"></th>
                            </tr>
                            <tr>
                                <td>DS0</td>
                                <td>
                                    <select id="ds0-sel" class="remote-button">
                                        <option value="" disabled="" selected="">...</option>
                                        <option value="0" l10n="select.off"></option>
                                        <option value="1">ECT</option>
                                        <option value="2">EOT</option>
                                        <option value="3">IAT</option>
                                        <option value="4">ATF</option>
                                        <option value="5">AAT</option>
                                        <option value="6">EXT</option>
                                    </select>
                                </td>
                                <td class="popup-target">
                                    <div id="ds0-color"><span id="ds0-stat"></span></div>
                                    <div class="popup popup-hidden"><span id="ds0-addr"></span></div>
                                </td>
                            </tr>
                            <tr>
                                <td>DS1</td>
                                <td>
                                    <select id="ds1-sel" class="remote-button">
                                        <option value="" disabled="" selected="">...</option>
                                        <option value="0" l10n="select.off"></option>
                                        <option value="1">ECT</option>
                                        <option value="2">EOT</option>
                                        <option value="3">IAT</option>
                                        <option value="4">ATF</option>
                                        <option value="5">AAT</option>
                                        <option value="6">EXT</option>
                                    </select>
                                </td>
                                <td class="popup-target">
                                    <div id="ds1-color"><span id="ds1-stat"></span></div>
                                    <div class="popup popup-hidden"><span id="ds1-addr"></span></div>
                                </td>
                            </tr>
                            <tr>
                                <td>DS2</td>
                                <td>
                                    <select id="ds2-sel" class="remote-button">
                                        <option value="" disabled="" selected="">...</option>
                                        <option value="0" l10n="select.off"></option>
                                        <option value="1">ECT</option>
                                        <option value="2">EOT</option>
                                        <option value="3">IAT</option>
                                        <option value="4">ATF</option>
                                        <option value="5">AAT</option>
                                        <option value="6">EXT</option>
                                    </select>
                                </td>
                                <td class="popup-target">
                                    <div id="ds2-color"><span id="ds2-stat"></span></div>
                                    <div class="popup popup-hidden"><span id="ds2-addr"></span></div>
                                </td>
                            </tr>
                            <tr>
                                <td>DS3</td>
                                <td>
                                    <select id="ds3-sel" class="remote-button">
                                        <option value="" disabled="" selected="">...</option>
                                        <option value="0" l10n="select.off"></option>
                                        <option value="1">ECT</option>
                                        <option value="2">EOT</option>
                                        <option value="3">IAT</option>
                                        <option value="4">ATF</option>
                                        <option value="5">AAT</option>
                                        <option value="6">EXT</option>
                                    </select>
                                </td>
                                <td class="popup-target">
                                    <div id="ds3-color"><span id="ds3-stat"></span></div>
                                    <div class="popup popup-hidden"><span id="ds3-addr"></span></div>
                                </td>
                            </tr>
                            <tr>
                                <td>DS4</td>
                                <td>
                                    <select id="ds4-sel" class="remote-button">
                                        <option value="" disabled="" selected="">...</option>
                                        <option value="0" l10n="select.off"></option>
                                        <option value="1">ECT</option>
                                        <option value="2">EOT</option>
                                        <option value="3">IAT</option>
                                        <option value="4">ATF</option>
                                        <option value="5">AAT</option>
                                        <option value="6">EXT</option>
                                    </select>
                                </td>
                                <td class="popup-target">
                                    <div id="ds4-color"><span id="ds4-stat"></span></div>
                                    <div class="popup popup-hidden"><span id="ds4-addr"></span></div>
                                </td>
                            </tr>
                            <tr>
                                <td l10n="inputs.dsx.sensors"><span id="ds-online"></span></td>
                                <td></td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="cntr">
                        <button id="ds-set-btn" class="btn btn-info btn-sm" l10n="btn.apply"></button>
                    </div>
            </div>

            <!-- Контейнер 3 -->
            <div class="card-remote">
                    <h1 l10n="inputs.bsx.inputs.title"></h1>
                    <table class="remote-table remote-table-horizontal">
                        <tbody>
                            <tr>
                                <td><span l10n="inputs.bsx.mode"></span></td>
                                <td>
                                    <select id="bsx-mode" class="remote-button">
                                        <option value="" disabled="" selected="">...</option>
                                        <option value="0" l10n="inputs.bsx.mode.gear"></option>
                                        <option value="1" l10n="inputs.bsx.mode.logic"></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td l10n="inputs.bsx.bs1.pullup"></td>
                                <td class="popup-target">
                                    <label class="switch"><input type="checkbox" id="bs1-pullup"><span class="slider round"></span></label>
                                    <div class="popup popup-hidden" l10n="inputs.bsx.pullup.description"></div>
                                </td>
                            </tr>
                            <tr>
                                <td l10n="inputs.bsx.bs2.pullup"></td>
                                <td class="popup-target">
                                    <label class="switch"><input type="checkbox" id="bs2-pullup"><span class="slider round"></span></label>
                                    <div class="popup popup-hidden" l10n="inputs.bsx.pullup.description"></div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="cntr">
                        <button id="bsx-set-btn" class="btn btn-info btn-sm" l10n="btn.apply"></button>
                    </div>
            </div>

            <!-- Контейнер 4 -->
            <div class="card-remote">
                    <h1 l10n="inputs.vfd.input.title"></h1>
                    <table class="remote-table remote-table-horizontal">
                        <tbody>
                            <tr>
                                <td><span class="popup-target" l10n="inputs.vfd.mode"><div class="popup popup-hidden" l10n="inputs.vfd.description"></div></span></td>
                                <td>
                                    <select id="vfd-mode" class="remote-button">
                                        <option value="" disabled="" selected="">...</option>
                                        <option value="0">RPM (R6/V6)</option>
                                        <option value="1">RPM (R4/V8)</option>
                                        <option value="2">OBD1</option>
                                        <option value="3">OBD1+RPM (R6/V6)</option>
                                        <option value="4">OBD1+RPM (R4/V8)</option>
                                        <option value="5">RPM (V2)</option>
                                    </select>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="cntr">
                        <button id="vfd-set-btn" class="btn btn-info btn-sm" l10n="btn.apply"></button>
                    </div>
            </div>
        </div>

        <div id="calibration" class="tab-content">
            <!-- Контейнер 1 -->
            <form>
                <div class="card-remote">
                    <h1 l10n="calibration.ntc.title"></h1>
                    <table class="remote-table remote-table-horizontal">
                        <tbody>
                            <tr>
                                <th colspan="2" class="popup-target"><span l10n="calibration.ntc.input.label"></span><div class="popup popup-hidden"><span l10n="calibration.ntc.input.description"></span></div></th>
                                <th style="font-weight:normal">
                                    <select id="aux-in" class="remote-button" onchange="select_aux(); step();">
                                        <option value="" disabled="">...</option>
                                        <option value="0" selected="">AUX0</option>
                                        <option value="1">AUX1</option>
                                        <option value="2">AUX2</option>
                                        <option value="3">AUX3</option>
                                        <option value="4">AUX4</option>
                                    </select>
                                </th>
                            </tr>
                            <tr>
                                <th></th>
                                <th id="nom" l10n="calibration.ntc.nominal.label"></th>
                                <th><span l10n="calibration.ntc.unit"></span></th>
                            </tr>
                            <tr>
                                <td>0</td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('aux-v0',step(),step())"></div>
                                        <input type="number" class="form-control" required="" id="aux-v0" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,limits_calibration());" onchange="vlim(this.id,limits_calibration());" step="0.01" min="0.01" max="5">
                                        <div class="value-button increase" onclick="iv('aux-v0',step(),step());vlim('aux-v0',limits_calibration())"></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('aux-t0',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="aux-t0" min="0" max="150" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,150);" onchange="vlim(this.id,150);">
                                        <div class="value-button increase" onclick="iv('aux-t0',1,1);vlim('aux-t0',150)"></div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>1</td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('aux-v1',step(),step())"></div>
                                        <input type="number" class="form-control" required="" id="aux-v1" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,limits_calibration());" onchange="vlim(this.id,limits_calibration());" step="0.01" min="0.01" max="5">
                                        <div class="value-button increase" onclick="iv('aux-v1',step(),step());vlim('aux-v1',limits_calibration())"></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('aux-t1',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="aux-t1" min="0" max="150" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,150);" onchange="vlim(this.id,150);">
                                        <div class="value-button increase" onclick="iv('aux-t1',1,1);vlim('aux-t1',150)"></div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>2</td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('aux-v2',step(),step())"></div>
                                        <input type="number" class="form-control" required="" id="aux-v2" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,limits_calibration());" onchange="vlim(this.id,limits_calibration());" step="0.01" min="0.01" max="5">
                                        <div class="value-button increase" onclick="iv('aux-v2',step(),step());vlim('aux-v2',limits_calibration())"></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('aux-t2',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="aux-t2" min="0" max="150" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,150);" onchange="vlim(this.id,150);">
                                        <div class="value-button increase" onclick="iv('aux-t2',1,1);vlim('aux-t2',150)"></div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>3</td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('aux-v3',step(),step())"></div>
                                        <input type="number" class="form-control" required="" id="aux-v3" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,limits_calibration());" onchange="vlim(this.id,limits_calibration());" step="0.01" min="0.01" max="5">
                                        <div class="value-button increase" onclick="iv('aux-v3',step(),step());vlim('aux-v3',limits_calibration())"></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('aux-t3',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="aux-t3" min="0" max="150" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,150);" onchange="vlim(this.id,150);">
                                        <div class="value-button increase" onclick="iv('aux-t3',1,1);vlim('aux-t3',150)"></div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>4</td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('aux-v4',step(),step())"></div>
                                        <input type="number" class="form-control" required="" id="aux-v4" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,limits_calibration());" onchange="vlim(this.id,limits_calibration());" step="0.01" min="0.01" max="5">
                                        <div class="value-button increase" onclick="iv('aux-v4',step(),step());vlim('aux-v4',limits_calibration())"></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('aux-t4',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="aux-t4" min="0" max="150" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,150);" onchange="vlim(this.id,150);">
                                        <div class="value-button increase" onclick="iv('aux-t4',1,1);vlim('aux-t4',150)"></div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>5</td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('aux-v5',step(),step())"></div>
                                        <input type="number" class="form-control" required="" id="aux-v5" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,limits_calibration());" onchange="vlim(this.id,limits_calibration());" step="0.01" min="0.01" max="5">
                                        <div class="value-button increase" onclick="iv('aux-v5',step(),step());vlim('aux-v5',limits_calibration())"></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('aux-t5',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="aux-t5" min="0" max="150" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,150);" onchange="vlim(this.id,150);">
                                        <div class="value-button increase" onclick="iv('aux-t5',1,1);vlim('aux-t5',150)"></div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>6</td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('aux-v6',step(),step())"></div>
                                        <input type="number" class="form-control" required="" id="aux-v6" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,limits_calibration());" onchange="vlim(this.id,limits_calibration());" step="0.01" min="0.01" max="5">
                                        <div class="value-button increase" onclick="iv('aux-v6',step(),step());vlim('aux-v6',limits_calibration())"></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('aux-t6',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="aux-t6" min="0" max="150" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,150);" onchange="vlim(this.id,150);">
                                        <div class="value-button increase" onclick="iv('aux-t6',1,1);vlim('aux-t6',150)"></div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>7</td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('aux-v7',step(),step())"></div>
                                        <input type="number" class="form-control" required="" id="aux-v7" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,limits_calibration());" onchange="vlim(this.id,limits_calibration());" step="0.01" min="0.01" max="5">
                                        <div class="value-button increase" onclick="iv('aux-v7',step(),step());vlim('aux-v7',limits_calibration())"></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('aux-t7',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="aux-t7" min="0" max="150" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,150);" onchange="vlim(this.id,150);">
                                        <div class="value-button increase" onclick="iv('aux-t7',1,1);vlim('aux-t7',150)"></div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>8</td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('aux-v8',step(),step())"></div>
                                        <input type="number" class="form-control" required="" id="aux-v8" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,limits_calibration());" onchange="vlim(this.id,limits_calibration());" step="0.01" min="0.01" max="5">
                                        <div class="value-button increase" onclick="iv('aux-v8',step(),step());vlim('aux-v8',limits_calibration())"></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('aux-t8',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="aux-t8" min="0" max="150" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,150);" onchange="vlim(this.id,150);">
                                        <div class="value-button increase" onclick="iv('aux-t8',1,1);vlim('aux-t8',150)"></div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>9</td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('aux-v9',step(),step())"></div>
                                        <input type="number" class="form-control" required="" id="aux-v9" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,limits_calibration());" onchange="vlim(this.id,limits_calibration());" step="0.01" min="0.01" max="5">
                                        <div class="value-button increase" onclick="iv('aux-v9',step(),step());vlim('aux-v9',limits_calibration())"></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('aux-t9',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="aux-t9" min="0" max="150" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,150);" onchange="vlim(this.id,150);">
                                        <div class="value-button increase" onclick="iv('aux-t9',1,1);vlim('aux-t9',150)"></div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="cntr">
                        <button id="cal-aux-set-btn" class="btn btn-info btn-sm" l10n="btn.apply" type="button"></button>
                    </div>
                </div>
            </form>

            <!-- Контейнер 2 -->
            <div class="card-remote">
                    <h1 l10n="calibration.pressure.title"></h1>
                    <table class="remote-table remote-table-horizontal">
                        <tbody>
                            <tr>
                                <td><span l10n="calibration.pressure.oil.label"></span></td>
                                <td>
                                    <select id="eop-sel" class="remote-button">
                                        <option value="" disabled="" selected="">...</option>
                                        <option value="0">10 bar</option>
                                        <option value="1">7 bar</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td><span l10n="calibration.pressure.fuel.label"></span></td>
                                <td>
                                    <select id="fp-sel" class="remote-button">
                                        <option value="" disabled="" selected="">...</option>
                                        <option value="0">10 bar</option>
                                        <option value="1">7 bar</option>
                                    </select>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="cntr">
                        <button id="ps-set-btn" class="btn btn-info btn-sm" l10n="btn.apply"></button>
                    </div>
            </div>

            <!-- Контейнер 3 -->
            <form>
                <div class="card-remote">
                    <h1 l10n="calibration.afr.title"></h1>
                    <table class="remote-table remote-table-horizontal">
                        <tbody>
                            <tr>
                                <td><span l10n="calibration.afr.0v.label"></span></td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('afr-0v',0,0.01)"></div>
                                        <input type="number" class="form-control" required="" id="afr-0v" min="0" max="25" step="0.01" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,25);" onchange="vlim(this.id,25);">
                                        <div class="value-button increase" onclick="iv('afr-0v',0,0.01);vlim('afr-0v',25)"></div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td><span l10n="calibration.afr.5v.label"></span></td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('afr-5v',0,0.01)"></div>
                                        <input type="number" class="form-control" required="" id="afr-5v" min="0" max="25" step="0.01" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,25);" onchange="vlim(this.id,25);">
                                        <div class="value-button increase" onclick="iv('afr-5v',0,0.01);vlim('afr-5v',25)"></div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target"><span l10n="calibration.afr.filter.label"></span><div class="popup popup-hidden"><span l10n="calibration.afr.filter.description"></span></div></td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('afr-filter',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="afr-filter" min="0" max="20" step="1" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,20);" onchange="vlim(this.id,20);">
                                        <div class="value-button increase" onclick="iv('afr-filter',1,1);vlim('afr-filter',20)"></div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="cntr">
                        <button id="afr-set-btn" class="btn btn-info btn-sm" l10n="btn.apply" type="button"></button>
                    </div>
                </div>
            </form>

            <!-- Контейнер 4 -->
            <form>
                <div class="card-remote">
                    <h1 l10n="calibration.custom-map.title"></h1>
                    <table class="remote-table remote-table-horizontal">
                        <tbody>
                            <tr>
                                <th></th>
                                <th l10n="datasum.min"></th>
                                <th l10n="datasum.max"></th>
                            </tr>
                            <tr>
                                <td>Volt</td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('map-0v',0,0.01)"></div>
                                        <input type="number" class="form-control" required="" id="map-0v" min="0" max="5" step="0.01" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,5);" onchange="vlim(this.id,5);">
                                        <div class="value-button increase" onclick="iv('map-0v',0,0.01);vlim('map-0v',5)"></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('map-1v',0,0.01)"></div>
                                        <input type="number" class="form-control" required="" id="map-1v" min="0" max="5" step="0.01" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,5);" onchange="vlim(this.id,5);">
                                        <div class="value-button increase" onclick="iv('map-1v',0,0.01);vlim('map-1v',5)"></div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target"><div class="popup popup-hidden"><span l10n="calibration.custom-map.kpa.description"></span></div>kPa</td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('map-0p',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="map-0p" min="0" max="1000" step="1" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,1000);" onchange="vlim(this.id,1000);">
                                        <div class="value-button increase" onclick="iv('map-0p',1,1);vlim('map-0p',1000)"></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('map-1p',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="map-1p" min="0" max="1000" step="1" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,1000);" onchange="vlim(this.id,1000);">
                                        <div class="value-button increase" onclick="iv('map-1p',1,1);vlim('map-1p',1000)"></div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="cntr">
                        <button id="map-set-btn" class="btn btn-info btn-sm" l10n="btn.apply" type="button"></button>
                    </div>
                </div>
            </form>
        </div>

        <div id="other" class="tab-content">
            <!-- Контейнер 1 -->
            <form>
                <div class="card-remote">
                    <h1 l10n="other.title"></h1>
                    <table class="remote-table remote-table-horizontal">
                        <tbody>
                            <tr>
                                <td l10n="other.voltmeter.correction"></td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('vlt-corr',0,0.01)"></div>
                                        <input type="number" class="form-control" required="" id="vlt-corr" min="0" max="2.55" step="0.01" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,2.55);" onchange="vlim(this.id,2.55);">
                                        <div class="value-button increase" onclick="iv('vlt-corr',0,0.01);vlim('vlt-corr',2.55)"></div> V
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target" l10n="other.rpm.multiplier"><div class="popup popup-hidden" l10n="other.rpm.multiplier.description"></div></td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('rpm-mult',0,0.01)"></div>
                                        <input type="number" class="form-control" required="" id="rpm-mult" min="0.01" max="1.99" step="0.01" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,1.99);" onchange="vlim(this.id,1.99);">
                                        <div class="value-button increase" onclick="iv('rpm-mult',0,0.01);vlim('rpm-mult',1.99)"></div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target" l10n="other.speed.multiplier"><div class="popup popup-hidden" l10n="other.speed.multiplier.description"></div></td>
                                <td>
                                    <div class="inc-dec">
                                        <div class="value-button decrease" onclick="dv('spd-mult',0,0.01)"></div>
                                        <input type="number" class="form-control" required="" id="spd-mult" min="0.01" max="1.99" step="0.01" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,1.99);" onchange="vlim(this.id,1.99);">
                                        <div class="value-button increase" onclick="iv('spd-mult',0,0.01);vlim('spd-mult',1.99)"></div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td l10n="other.pim.signal.mode"></td>
                                <td>
                                    <select id="pim-mode" class="remote-button">
                                        <option value="" disabled="" selected="">...</option>
                                        <option value="0">Static</option>
                                        <option value="1">MAP</option>
                                        <option value="2">MAF</option>
                                        <option value="3">ECT</option>
                                        <option value="4">EOT</option>
                                        <option value="5">IAT</option>
                                        <option value="6">ATF</option>
                                        <option value="7">AAT</option>
                                        <option value="8">EXT</option>
                                        <option value="9">BST</option>
                                        <option value="10">FLP</option>
                                        <option value="11">EOP</option>
                                        <option value="12">AFR</option>
                                        <option value="13">EGT</option>
                                        <option value="14">RPM</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td l10n="other.pim.signal.output"></td>
                                <td>
                                    <div class="inc-dec" id="pim-off" style="pointer-events: auto;">
                                        <div class="value-button decrease" onclick="dv('pim-out',1,1)"></div>
                                        <input type="number" class="form-control" required="" id="pim-out" min="0" max="85" placeholder="..." onkeydown="return nolet(event);" onkeyup="vlim(this.id,85);" onchange="vlim(this.id,85);">
                                        <div class="value-button increase" onclick="iv('pim-out',1,1);vlim('pim-out',85)"></div> %
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target" l10n="other.motorhours.mode"><div class="popup popup-hidden" l10n="other.motorhours.mode.description"></div></td>
                                <td>
                                    <select id="mh-mode" class="remote-button">
                                        <option value="" disabled="" selected="">...</option>
                                        <option value="0">Simple</option>
                                        <option value="1">Advanced</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td class="popup-target" l10n="other.motorhours.history"><div class="popup popup-hidden" l10n="other.motorhours.history.description"></div></td>
                                <td><span id="mr-hist">1092h</span></td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="cntr">
                        <button id="misc-set-btn" class="btn btn-info btn-sm" l10n="btn.apply" type="button"></button>
                    </div>
                </div>
            </form>
        </div>

        <div id="config" class="tab-content">
            <!-- Контейнер 1 -->
            <div class="card-remote" id="log">
                <h1 l10n="config.title"></h1>
                <div id="" style="display: flex; justify-content: center;">
                    <div style="display:flex; justify-content:center;">
                             <input class="btn btn-default" style="border-radius:5px;width:100%" type="file" name="file[]" id="cfgFile" onchange="checkCfg();" accept=".b64">
                    </div>
                </div>
                <div class="cntr" style="gap:10px">
                    <button id="config-upload-btn" class="btn btn-info btn-sm" l10n="btn.upload" type="button"></button>
                    <button id="config-download-btn" class="btn btn-info btn-sm" l10n="btn.download" type="button"></button>
                </div>
            </div>

        </div>
    </div>

    <script>
    let data = [<?php echo $data; ?>];
    let stor_data = JSON.parse(localStorage.getItem("data") || "[]");
    const blocked = <?php echo $blocked; ?> === 0 ? true : false;

    if (JSON.stringify(data) !== JSON.stringify(stor_data)) {
        localStorage.setItem("data", JSON.stringify(data));
        stor_data = JSON.parse(JSON.stringify(data));
    }
    const token = '<?php echo htmlspecialchars($token); ?>';
    document.addEventListener('DOMContentLoaded', function() {
        const tabs = document.querySelectorAll('.tab');
        const tabContents = document.querySelectorAll('.tab-content');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const tabId = tab.getAttribute('data-tab');

                tabs.forEach(t => t.classList.remove('active'));
                tabContents.forEach(tc => tc.classList.remove('active'));

                tab.classList.add('active');
                document.getElementById(tabId).classList.add('active');
            });
        });
    <?php if ($uid) { ?>
        const langSwitch = document.getElementById('lang-switch');
        const selectedLang = document.getElementById('selected-lang');
        const langOptions = document.getElementById('lang-options');

        function closeDropdown() {
            langOptions.classList.remove('show');
        }

        selectedLang.addEventListener('click', function(event) {
            event.stopPropagation();
            if (langOptions.classList.contains('show')) {
              closeDropdown();
            } else {
              langOptions.classList.add('show');
            }
        });

        langOptions.querySelectorAll('li').forEach(option => {
            option.addEventListener('click', function() {
              const selectedValue = this.getAttribute('data-value');
              const selectedText = this.textContent;
              closeDropdown();

              fetch(`translations.php?lang=${selectedValue}`)
                .then(() => {
                    localization.setLang(selectedValue);
                    location.reload();
                })
                .catch(error => {
                  console.error('Error:', error);
                });
            });
        });

        document.addEventListener('click', function(event) {
            if (!langSwitch.contains(event.target)) {
              closeDropdown();
            }
        });

    <?php } ?>
        let dropArea = document.getElementById('log');
        let fl = document.getElementById('cfgFile');

        dropArea.addEventListener('drop', drop);
        dropArea.addEventListener('dragover', dragover);
        dropArea.addEventListener('dragleave', dragleave);

        function drop(event) {
            event.preventDefault();
            dropArea.style.border = '';
            fl.files = event.dataTransfer.files;
            checkCfg();
        }

        function dragover(event) {
            event.preventDefault();
            dropArea.style.borderColor = '#0eff00';
        }

        function dragleave(event) {
            event.preventDefault();
            dropArea.style.borderColor = '';
        }
    });

    <?php if (!$uid) { ?>
    function shareRemote() {
      const uid = "<?php echo $_SESSION['uid']; ?>";
      $(".fetch-data").css("display", "block");
      $(".share-img").css("pointer-events", "none");

      fetch('sign.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ uid })
      })
      .then(response => response.json())
      .then(result => {
        if (result.signature) {
            const sig = result.signature;
            const url = `${window.location.origin}/share_remote.php?uid=${encodeURIComponent(uid)}&sig=${sig}`;
            if (navigator.share) {
                return navigator.share({
                  text: '',
                  url: url,
                }).finally(() => {
                    $(".fetch-data").css("display", "none");
                    $(".share-img").css("pointer-events", "auto");
                });
            } else {
                $(".fetch-data").css("display", "none");
                $(".share-img").css("pointer-events", "auto");
                let dialogOpt = {
                title : localization.key['dialog.confirm'],
                message: localization.key['share.dialog.text'],
                btnClassSuccessText: localization.key['btn.yes'],
                btnClassFailText: localization.key['btn.no'],
                btnClassFail: "btn btn-info btn-sm",
                onResolve: function() {
                        copyToClipboard(url);
                    }
                };
                redDialog.make(dialogOpt);
            }
        } else {
            $(".fetch-data").css("display", "none");
            serverError(result.error);
        }
      })
      .catch(err => {
        $(".fetch-data").css("display", "none");
        serverError(err);
      });
    }
    <?php } ?>
    </script>
<?php } else { ?>
        <div id="right-container" class="col-md-auto col-xs-12">
            <div class="login" style="text-align:center; width:fit-content; margin: 50px auto">
                <h4 l10n="nodata.show"></h4>
                <h6 l10n="remote.empty.label"></h6>
            </div>
        </div>
<?php } ?>
</body>
</html>
