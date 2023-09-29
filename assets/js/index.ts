import './FormHandler';

document.addEventListener('keydown', function(event) {
    // Check if the pressed key is "Enter"
    if (event.keyCode === 13 || event.key === 'Enter') {
        // Log the triggered element to the console
        console.log('Element that triggered Enter key:', event.target);
    }
});
