(function(){
	function qs(scope, sel){ return (scope||document).querySelector(sel); }
	function on(el, ev, fn){ el && el.addEventListener(ev, fn); }

	document.addEventListener('submit', async function(e){
		const form = e.target.closest('.nsb-form');
		if(!form) return;
		e.preventDefault();

		const email = qs(form, 'input[name="email"]').value.trim();
		const hp    = qs(form, 'input[name="company"]').value.trim();
		const msgEl = qs(form, '.nsb-msg');
		msgEl.textContent = '';

		if(!email){ msgEl.textContent = 'Please enter your email address.'; return; }

		try{
			const res = await fetch(NSB.restUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': NSB.nonce
				},
				body: JSON.stringify({ email, company: hp })
			});

			const data = await res.json();
			if(!res.ok){ throw new Error(data?.message || 'Something went wrong'); }
			msgEl.textContent = data.message || 'Thanks! Please check your email to confirm.';
			form.reset();
		} catch(err){
			msgEl.textContent = err.message || 'Sorry, there was a problem. Please try again.';
		}
	}, {capture:true});
})();