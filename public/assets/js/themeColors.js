const handleThemeUpdate = (cssVars) => {
    const root = document.querySelector(':root');
    const keys = Object.keys(cssVars);
    keys.forEach(key => {
        root.style.setProperty(key, cssVars[key]);
    });
}
const hex2rgb = (hex) => {
    const r = parseInt(hex.slice(1, 3), 16)
    const g = parseInt(hex.slice(3, 5), 16)
    const b = parseInt(hex.slice(5, 7), 16)
    return [r, g, b]
}
function dynamicPrimaryColor(primaryColor) {
    'use strict'

    primaryColor.forEach((item) => {
        item.addEventListener('input', (e) => {
            document.querySelector('html').style.setProperty('--primary-rgb', hex2rgb(e.target.value));
        });
    });
}
function darkdynamicPrimaryColor(darkprimaryColor) {
    'use strict'

    darkprimaryColor.forEach((item) => {
        item.addEventListener('input', (e) => {
            document.querySelector('html').style.setProperty('--primary-rgb', hex2rgb(e.target.value));
        });
    });
}

function imageBgdynamicPrimaryColor(imageprimaryColor) {
    'use strict'

    imageprimaryColor.forEach((item) => {
        item.addEventListener('input', (e) => {
            document.querySelector('html').style.setProperty('--transparent-bg-rgb', hex2rgb(e.target.value));
        });
    });
}
function transparentdynamicPrimaryColor(primaryColor) {
    'use strict'

    primaryColor.forEach((item) => {
        item.addEventListener('input', (e) => {

            document.querySelector('html').style.setProperty('--primary-rgb', hex2rgb(e.target.value));
        });
    });
}

(function () {
    'use strict'

    // Light theme color picker
    const dynamicPrimaryLight = document.querySelectorAll('input.color-primary-light');

    dynamicPrimaryColor(dynamicPrimaryLight);

    // dark theme color picker
    const DarkDynamicPrimaryLight = document.querySelectorAll('input.color-primary-dark');

    darkdynamicPrimaryColor(DarkDynamicPrimaryLight);

    // tranparent theme color picker
    const transparentDynamicPrimaryLight = document.querySelectorAll('input.color-primary-transparent');

    transparentdynamicPrimaryColor(transparentDynamicPrimaryLight);

    // tranparent theme bgcolor picker
    const transparentDynamicPBgLight = document.querySelectorAll('input.color-bg-transparent');

    imageBgdynamicPrimaryColor(transparentDynamicPBgLight);

    localStorageBackup();

    $('#myonoffswitch1').on('click', function () {
        document.querySelector('body')?.classList.remove('dark-mode');
        document.querySelector('body')?.classList.remove('transparent-mode');
        document.querySelector('body')?.classList.remove('bg-img1');
        document.querySelector('body')?.classList.remove('bg-img2');
        document.querySelector('body')?.classList.remove('bg-img3');
        document.querySelector('body')?.classList.remove('bg-img4');

        localStorage.removeItem('vistaBgImage');
        $('#myonoffswitch1').prop('checked', true);

        localStorage.setItem('vistalightMode', true);
        localStorage.removeItem('vistadarkMode');
        localStorage.removeItem('vistatransparentMode');
    })
    $('#myonoffswitch2').on('click', function () {
        document.querySelector('body')?.classList.remove('light-mode');
        document.querySelector('body')?.classList.remove('transparent-mode');
        document.querySelector('body')?.classList.remove('bg-img1');
        document.querySelector('body')?.classList.remove('bg-img2');
        document.querySelector('body')?.classList.remove('bg-img3');
        document.querySelector('body')?.classList.remove('bg-img4');

        localStorage.setItem('vistadarkMode', true);
        localStorage.removeItem('vistalightMode');
        localStorage.removeItem('vistatransparentMode');

        localStorage.removeItem('vistaBgImage');
        $('#myonoffswitch2').prop('checked', true);
    })
    $('#myonoffswitchTransparent').on('click', function () {
        document.querySelector('body')?.classList.remove('dark-mode');
        document.querySelector('body')?.classList.remove('light-mode');
        document.querySelector('body')?.classList.remove('bg-img1');
        document.querySelector('body')?.classList.remove('bg-img2');
        document.querySelector('body')?.classList.remove('bg-img3');
        document.querySelector('body')?.classList.remove('bg-img4');

        localStorage.removeItem('vistaBgImage');
        $('#myonoffswitchTransparent').prop('checked', true);

        localStorage.setItem('vistatransparentMode', true);
        localStorage.removeItem('vistalightMode');
        localStorage.removeItem('vistadarkMode');
    })

})();

// triggers on changing the color picker
function changePrimaryColor() {
    'use strict'

    $('#myonoffswitch3').prop('checked', true);
    $('#myonoffswitch6').prop('checked', true);
    checkOptions();

    var userColor = document.getElementById('colorID').value;
    localStorage.setItem('vistaprimaryColor', hex2rgb(userColor));

    // removing dark theme properties
    localStorage.removeItem('vistadarkPrimary')
    localStorage.removeItem('vistatransparentBgColor');
    localStorage.removeItem('vistatransparentThemeColor');
    localStorage.removeItem('vistatransparentPrimary');
    localStorage.removeItem('vistatransparentBgImgPrimary');
    localStorage.removeItem('vistatransparentBgImgprimaryTransparent');
    localStorage.removeItem('vistadarkprimaryTransparent');
    document.querySelector('body').classList.add('light-mode');
    document.querySelector('body').classList.remove('transparent-mode');
    document.querySelector('body').classList.remove('dark-mode');
    localStorage.removeItem('vistaBgImage');

    $('#myonoffswitch1').prop('checked', true);
    names()

    localStorage.setItem('vistalightMode', true);
    localStorage.removeItem('vistadarkMode');
    localStorage.removeItem('vistatransparentMode');
}

function darkPrimaryColor() {
    'use strict'
    var userColor = document.getElementById('darkPrimaryColorID').value;
    localStorage.setItem('vistadarkPrimary', hex2rgb(userColor));

    $('#myonoffswitch5').prop('checked', true);
    $('#myonoffswitch8').prop('checked', true);
    checkOptions();

    // removing light theme data 
    localStorage.removeItem('vistaprimaryColor')
    localStorage.removeItem('vistaprimaryHoverColor')
    localStorage.removeItem('vistaprimaryBorderColor')
    localStorage.removeItem('vistaprimaryTransparent');
    localStorage.removeItem('vistatransparentBgImgPrimary');
    localStorage.removeItem('vistatransparentBgImgprimaryTransparent');

    localStorage.removeItem('vistatransparentBgColor');
    localStorage.removeItem('vistatransparentThemeColor');
    localStorage.removeItem('vistatransparentPrimary');
    localStorage.removeItem('vistaBgImage');

    document.querySelector('body').classList.add('dark-mode');
    document.querySelector('body').classList.remove('light-mode');
    document.querySelector('body').classList.remove('transparent-mode');

    $('#myonoffswitch2').prop('checked', true);
    names()

    localStorage.setItem('vistadarkMode', true);
    localStorage.removeItem('vistalightMode');
    localStorage.removeItem('vistatransparentMode');
}

function transparentPrimaryColor() {
    'use strict'

    $('#myonoffswitch3').prop('checked', false);
    $('#myonoffswitch6').prop('checked', false);
    $('#myonoffswitch5').prop('checked', false);
    $('#myonoffswitch8').prop('checked', false);

    var userColor = document.getElementById('transparentPrimaryColorID').value;
    localStorage.setItem('vistatransparentPrimary', hex2rgb(userColor));

    // removing light theme data 
    localStorage.removeItem('vistadarkPrimary');
    localStorage.removeItem('vistaprimaryColor')
    localStorage.removeItem('vistaprimaryHoverColor')
    localStorage.removeItem('vistaprimaryBorderColor')
    localStorage.removeItem('vistaprimaryTransparent');
    localStorage.removeItem('vistatransparentBgImgPrimary');
    localStorage.removeItem('vistatransparentBgImgprimaryTransparent');
    document.querySelector('body').classList.add('transparent-mode');
    document.querySelector('body').classList.remove('light-mode');
    document.querySelector('body').classList.remove('dark-mode');
    document.querySelector('body')?.classList.remove('bg-img1');
    document.querySelector('body')?.classList.remove('bg-img2');
    document.querySelector('body')?.classList.remove('bg-img3');
    document.querySelector('body')?.classList.remove('bg-img4');

    $('#myonoffswitchTransparent').prop('checked', true);
    checkOptions();
    names()

    localStorage.setItem('vistatransparentMode', true);
    localStorage.removeItem('vistalightMode');
    localStorage.removeItem('vistadarkMode');
}

function transparentBgImgPrimaryColor() {
    'use strict'

    $('#myonoffswitch3').prop('checked', false);
    $('#myonoffswitch6').prop('checked', false);
    $('#myonoffswitch5').prop('checked', false);
    $('#myonoffswitch8').prop('checked', false);

    var userColor = document.getElementById('transparentBgImgPrimaryColorID').value;
    localStorage.setItem('vistatransparentBgImgPrimary', hex2rgb(userColor));
    if (
        document.querySelector('body')?.classList.contains('bg-img1') == false &&
        document.querySelector('body')?.classList.contains('bg-img2') == false &&
        document.querySelector('body')?.classList.contains('bg-img3') == false &&
        document.querySelector('body')?.classList.contains('bg-img4') == false
    ) {
        document.querySelector('body')?.classList.add('bg-img1');
        localStorage.setItem('vistaBgImage', 'bg-img1')
    }
    // removing light theme data 
    localStorage.removeItem('vistadarkPrimary');
    localStorage.removeItem('vistaprimaryColor')
    localStorage.removeItem('vistaprimaryHoverColor')
    localStorage.removeItem('vistaprimaryBorderColor')
    localStorage.removeItem('vistaprimaryTransparent');
    localStorage.removeItem('vistadarkprimaryTransparent');
    localStorage.removeItem('vistatransparentPrimary')
    localStorage.removeItem('vistatransparentprimaryTransparent');
    document.querySelector('body').classList.add('transparent-mode');
    document.querySelector('body')?.classList.remove('light-mode');
    document.querySelector('body')?.classList.remove('dark-mode');

    $('#myonoffswitchTransparent').prop('checked', true);
    checkOptions();
    names();

    localStorage.setItem('vistatransparentMode', true);
    localStorage.removeItem('vistalightMode');
    localStorage.removeItem('vistadarkMode');
}


function transparentBgColor() {
    'use strict'

    $('#myonoffswitch3').prop('checked', false);
    $('#myonoffswitch6').prop('checked', false);
    $('#myonoffswitch5').prop('checked', false);
    $('#myonoffswitch8').prop('checked', false);

    var userColor = document.getElementById('transparentBgColorID').value;
    localStorage.setItem('vistatransparentBgColor', hex2rgb(userColor));
    localStorage.removeItem('vistatransparentBgImgPrimary');
    localStorage.removeItem('vistatransparentBgImgprimaryTransparent');

    // removing light theme data 
    localStorage.removeItem('vistadarkPrimary');
    localStorage.removeItem('vistaprimaryColor')
    localStorage.removeItem('vistaprimaryHoverColor')
    localStorage.removeItem('vistaprimaryBorderColor')
    localStorage.removeItem('vistaprimaryTransparent');
    localStorage.removeItem('vistaBgImage');
    document.querySelector('body').classList.add('transparent-mode');
    document.querySelector('body').classList.remove('light-mode');
    document.querySelector('body').classList.remove('dark-mode');
    document.querySelector('body')?.classList.remove('bg-img1');
    document.querySelector('body')?.classList.remove('bg-img2');
    document.querySelector('body')?.classList.remove('bg-img3');
    document.querySelector('body')?.classList.remove('bg-img4');

    $('#myonoffswitchTransparent').prop('checked', true);
    checkOptions();

    localStorage.setItem('vistatransparentMode', true);
    localStorage.removeItem('vistalightMode');
    localStorage.removeItem('vistadarkMode');
}


function bgImage(e) {
    'use strict'

    $('#myonoffswitch3').prop('checked', false);
    $('#myonoffswitch6').prop('checked', false);
    $('#myonoffswitch5').prop('checked', false);
    $('#myonoffswitch8').prop('checked', false);
    let imgID = e.getAttribute('class');
    localStorage.setItem('vistaBgImage', imgID);

    // removing light theme data 
    localStorage.removeItem('vistadarkPrimary');
    localStorage.removeItem('vistaprimaryColor')
    localStorage.removeItem('vistatransparentBgColor');
    localStorage.removeItem('vistatransparentThemeColor');
    localStorage.removeItem('vistatransparentprimaryTransparent');
    document.querySelector('body').classList.add('transparent-mode');
    document.querySelector('body')?.classList.remove('light-mode');
    document.querySelector('body')?.classList.remove('dark-mode');

    $('#myonoffswitchTransparent').prop('checked', true);
    checkOptions();

    localStorage.setItem('vistatransparentMode', true);
    localStorage.removeItem('vistalightMode');
    localStorage.removeItem('vistadarkMode');
}

function localStorageBackup() {
    'use strict'

    // if there is a value stored, update color picker and background color
    // Used to retrive the data from local storage
    if (localStorage.vistaprimaryColor) {
        if (document.getElementById('colorID')) {
            document.getElementById('colorID').value = localStorage.vistaprimaryColor;
        }
        document.querySelector('html').style.setProperty('--primary-rgb', localStorage.vistaprimaryColor);
        document.querySelector('body')?.classList.add('light-mode');
        document.querySelector('body')?.classList.remove('dark-mode');
        document.querySelector('body')?.classList.remove('transparent-mode');

        $('#myonoffswitch3').prop('checked', true);
        $('#myonoffswitch6').prop('checked', true);
        $('#myonoffswitch1').prop('checked', true);
    }

    if (localStorage.vistadarkPrimary) {
        if (document.getElementById('darkPrimaryColorID')) {
            document.getElementById('darkPrimaryColorID').value = localStorage.vistadarkPrimary;
        }
        document.querySelector('html').style.setProperty('--primary-rgb', localStorage.vistadarkPrimary);
        document.querySelector('body')?.classList.add('dark-mode');
        document.querySelector('body')?.classList.remove('light-mode');
        document.querySelector('body')?.classList.remove('transparent-mode');

        $('#myonoffswitch2').prop('checked', true);

    }


    if (localStorage.vistatransparentPrimary) {
        if (document.getElementById('transparentPrimaryColorID')) {
            document.getElementById('transparentPrimaryColorID').value = localStorage.vistatransparentPrimary;
        }
        document.querySelector('html').style.setProperty('--primary-rgb', localStorage.vistatransparentPrimary);
        document.querySelector('body')?.classList.add('transparent-mode');
        document.querySelector('body')?.classList.remove('dark-mode');
        document.querySelector('body')?.classList.remove('light-mode');

        $('#myonoffswitchTransparent').prop('checked', true);
    }

    if (localStorage.vistatransparentBgImgPrimary) {
        if (document.getElementById('transparentBgImgPrimaryColorID')) {
            document.getElementById('transparentBgImgPrimaryColorID').value = localStorage.vistatransparentBgImgPrimary;
        }
        document.querySelector('html').style.setProperty('--primary-rgb', localStorage.vistatransparentBgImgPrimary);
        document.querySelector('body')?.classList.add('transparent-mode');
        document.querySelector('body')?.classList.remove('dark-mode');
        document.querySelector('body')?.classList.remove('light-mode');

        $('#myonoffswitchTransparent').prop('checked', true);
    }

    if (localStorage.vistatransparentBgColor) {
        if (document.getElementById('transparentBgColorID')) {
            document.getElementById('transparentBgColorID').value = localStorage.vistatransparentBgColor;
        }
        document.querySelector('html').style.setProperty('--transparent-bg-rgb', localStorage.vistatransparentBgColor);
        document.querySelector('body').classList.add('transparent-mode');
        document.querySelector('body').classList.remove('dark-mode');
        document.querySelector('body').classList.remove('light-mode');


        $('#myonoffswitchTransparent').prop('checked', true);
    }
    if (localStorage.vistaBgImage) {
        document.querySelector('body')?.classList.add('transparent-mode');
        let bgImg = localStorage.vistaBgImage.split(' ')[0];
        document.querySelector('body')?.classList.add(bgImg);
        document.querySelector('body')?.classList.remove('dark-mode');
        document.querySelector('body')?.classList.remove('light-mode');

        $('#myonoffswitchTransparent').prop('checked', true);
    }
    if (localStorage.vistalightMode) {
        document.querySelector('body')?.classList.add('light-mode');
        document.querySelector('body')?.classList.remove('dark-mode');
        document.querySelector('body')?.classList.remove('transparent-mode');
        $('#myonoffswitch1').prop('checked', true);
        $('#myonoffswitch3').prop('checked', true);
        $('#myonoffswitch6').prop('checked', true);
    }
    if (localStorage.vistadarkMode) {
        document.querySelector('body')?.classList.remove('light-mode');
        document.querySelector('body')?.classList.add('dark-mode');
        document.querySelector('body')?.classList.remove('transparent-mode');
        $('#myonoffswitch2').prop('checked', true);
        $('#myonoffswitch5').prop('checked', true);
        $('#myonoffswitch8').prop('checked', true);
    }
    if (localStorage.vistatransparentMode) {
        document.querySelector('body')?.classList.remove('light-mode');
        document.querySelector('body')?.classList.remove('dark-mode');
        document.querySelector('body')?.classList.add('transparent-mode');
        $('#myonoffswitchTransparent').prop('checked', true);
        $('#myonoffswitch3').prop('checked', false);
        $('#myonoffswitch6').prop('checked', false);
        $('#myonoffswitch5').prop('checked', false);
        $('#myonoffswitch8').prop('checked', false);
    }
    if (localStorage.vistahorizontal) {
        document.querySelector('body').classList.add('horizontal')
    }
    if (localStorage.vistahorizontalHover) {
        document.querySelector('body').classList.add('horizontal-hover')
    }
    if (localStorage.vistartl) {
        document.querySelector('body').classList.add('rtl')
    }
    if (localStorage.vistaclosedmenu) {
        document.querySelector('body').classList.add('closed-leftmenu')
    }
    if (localStorage.vistaicontextmenu) {
        document.querySelector('body').classList.add('icontext-menu')
    }
    if (localStorage.vistasideiconmenu) {
        document.querySelector('body').classList.add('icon-overlay')
    }
    if (localStorage.vistahoversubmenu) {
        document.querySelector('body').classList.add('hover-submenu')
    }
    if (localStorage.vistahoversubmenu1) {
        document.querySelector('body').classList.add('hover-submenu1')
    }
    if (localStorage.vistadoublemenu) {
        document.querySelector('body').classList.add('double-menu')
    }
    if (localStorage.vistadoublemenutabs) {
        document.querySelector('body').classList.add('double-menu-tabs')
    }
    if (localStorage.vistascrollable) {
        document.querySelector('body').classList.add('scrollable-layout')
    }
    if (localStorage.vistaboxedwidth) {
        document.querySelector('body').classList.add('layout-boxed')
    }
    if (localStorage.vistalightheader) {
        document.querySelector('body').classList.add('header-light')
    }
    if (localStorage.vistacolorheader) {
        document.querySelector('body').classList.add('color-header')
    }
    if (localStorage.vistadarkheader) {
        document.querySelector('body').classList.add('dark-header')
    }
    if (localStorage.vistagradientheader) {
        document.querySelector('body').classList.add('gradient-header')
    }

    if (localStorage.vistalightmenu) {
        document.querySelector('body').classList.add('light-menu')
    }
    if (localStorage.vistacolormenu) {
        document.querySelector('body').classList.add('color-menu')
    }
    if (localStorage.vistadarkmenu) {
        document.querySelector('body').classList.add('dark-menu')
    }
    if (localStorage.vistagradientmenu) {
        document.querySelector('body').classList.add('gradient-menu')
    }
    if (localStorage.vistadefaultlogo) {
        document.querySelector('body').classList.add('default-logo')
    }
    if (localStorage.vistacenterlogo) {
        document.querySelector('body').classList.add('center-logo')
    }


}

// to check the value is hexa or not
const isValidHex = (hexValue) => /^#([A-Fa-f0-9]{3,4}){1,2}$/.test(hexValue)

const getChunksFromString = (st, chunkSize) => st.match(new RegExp(`.{${chunkSize}}`, "g"))
// convert hex value to 256
const convertHexUnitTo256 = (hexStr) => parseInt(hexStr.repeat(2 / hexStr.length), 16)
// get alpha value is equla to 1 if there was no value is asigned to alpha in function
const getAlphafloat = (a, alpha) => {
    if (typeof a !== "undefined") { return a / 255 }
    if ((typeof alpha != "number") || alpha < 0 || alpha > 1) {
        return 1
    }
    return alpha
}
// convertion of hex code to rgba code 
function hexToRgba(hexValue, alpha) {
    'use strict'

    if (!isValidHex(hexValue)) { return null }
    const chunkSize = Math.floor((hexValue.length - 1) / 3)
    const hexArr = getChunksFromString(hexValue.slice(1), chunkSize)
    const [r, g, b, a] = hexArr.map(convertHexUnitTo256)
    return `rgba(${r}, ${g}, ${b}, ${getAlphafloat(a, alpha)})`
}


let myVarVal, myVarVal1, myVarVal2, myVarVal3,primaryColorVal

function names() {
    'use strict'

    primaryColorVal = getComputedStyle(document.documentElement).getPropertyValue('--primary-rgb').trim();

    //get variable
    myVarVal = localStorage.getItem("vistaprimaryColor") || localStorage.getItem("vistadarkPrimary") || localStorage.getItem("vistatransparentPrimary") || localStorage.getItem("vistatransparentBgImgPrimary") ||primaryColorVal;
    myVarVal1 = localStorage.getItem("vistaprimaryColor") || localStorage.getItem("vistadarkPrimary") || localStorage.getItem("vistatransparentPrimary") || localStorage.getItem("vistatransparentBgImgPrimary") || "#007F6E";

    if (document.querySelector('#transactions') !== null) {
        index(myVarVal, myVarVal1);
    };
    if (document.querySelector('#reportChart') !== null) {
        index(myVarVal, myVarVal1);
    }; 
    if (document.querySelector('#Audience') !== null) {
        index(myVarVal, myVarVal1);
    } 
    if (document.querySelector('#crm-total-customers') !== null) {
        index(myVarVal, myVarVal1);
    } 
   
}
names();
