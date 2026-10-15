document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('emi-form');
  if (!form) return;

  var priceInput = document.getElementById('emi-price');
  var downPctInput = document.getElementById('emi-down-pct');
  var rateInput = document.getElementById('emi-rate');
  var yearsInput = document.getElementById('emi-years');
  var resultEl = document.getElementById('emi-result');
  var breakdownEl = document.getElementById('emi-breakdown');

  function formatINR(n) {
    n = Math.round(n);
    var neg = n < 0;
    n = Math.abs(n);
    var str = String(n);
    var lastThree = str.substring(str.length - 3);
    var rest = str.substring(0, str.length - 3);
    if (rest !== '') {
      rest = rest.replace(/\B(?=(\d{2})+(?!\d)$)/g, ',');
      lastThree = ',' + lastThree;
    }
    return (neg ? '-' : '') + '₹' + rest + lastThree;
  }

  function calculate() {
    var price = parseFloat(priceInput.value) || 0;
    var downPct = parseFloat(downPctInput.value) || 0;
    var rate = parseFloat(rateInput.value) || 0;
    var years = parseFloat(yearsInput.value) || 0;

    var downPayment = price * (downPct / 100);
    var principal = Math.max(0, price - downPayment);
    var monthlyRate = rate / 12 / 100;
    var months = years * 12;

    var emi = 0;
    if (principal > 0 && months > 0) {
      if (monthlyRate > 0) {
        var factor = Math.pow(1 + monthlyRate, months);
        emi = (principal * monthlyRate * factor) / (factor - 1);
      } else {
        emi = principal / months;
      }
    }

    var totalPayment = emi * months;
    var totalInterest = Math.max(0, totalPayment - principal);

    resultEl.innerHTML = '<div class="emi-amount">' + formatINR(emi) + '<span>/month</span></div>';

    breakdownEl.innerHTML =
      '<div class="emi-breakdown-row"><span>Loan Amount</span><strong>' + formatINR(principal) + '</strong></div>' +
      '<div class="emi-breakdown-row"><span>Down Payment</span><strong>' + formatINR(downPayment) + '</strong></div>' +
      '<div class="emi-breakdown-row"><span>Total Interest</span><strong>' + formatINR(totalInterest) + '</strong></div>' +
      '<div class="emi-breakdown-row"><span>Total repaid to lender (loan + interest)</span><strong>' + formatINR(totalPayment) + '</strong></div>';

    var advisorLink = document.getElementById('emi-advisor-link');
    if (advisorLink && price > 0) {
      var msg = "Hi, I'd like to discuss financing. I tried the EMI calculator with: price " + formatINR(price) +
        ', down payment ' + downPct + '% (' + formatINR(downPayment) + '), rate ' + rate + '% p.a., tenure ' + years +
        ' years — estimated EMI ' + formatINR(emi) + '/month.\n\n(Sent from the Kutumb website, page: emi-calculator)';
      var base = advisorLink.getAttribute('data-base');
      if (base) advisorLink.href = base + '?text=' + encodeURIComponent(msg);
    }
  }

  ['input', 'change'].forEach(function (evt) { form.addEventListener(evt, calculate); });
  calculate();
});
