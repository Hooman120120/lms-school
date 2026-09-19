function validateIranianNationalId(nationalId) {
    nationalId = nationalId.toString().replace(/[^0-9]/g, '');
    if (nationalId.length !== 10) {
        return false;
    }
    const invalidPatterns = [
        '0000000000' , '1111111111' , '2222222222' , '3333333333' , '4444444444' , '5555555555' , '6666666666' , '7777777777' , '8888888888' , '9999999999'
    ];
    if (invalidPatterns.includes(nationalId)) {
        return false
    }
    const check = parseInt(nationalId[9]);
    let sum = 0;
    for (let i = 0; i < 9; i++) {
        sum += parseInt(nationalId[i]) * (10 - i);
    }
    const remainder = sum % 11;
    const controlDigit = remainder < 2 ? remainder : 11 - remainder;
    return controlDigit === check;
}
// alert
