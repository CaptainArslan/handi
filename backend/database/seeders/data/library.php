<?php

/*
| Seed content for the dish library.
| [name, category key, note, image key (bundled art; null = category default), weather[], effort, cost, usual meal]
| weather: hot | mild | cold | rainy | any · effort: quick | medium | heavy · cost: low | medium | high
| usual meal: breakfast | lunch | dinner | both
*/

return [
    // Vegetables
    ['Bhindi', 'vegetables', 'Summer favourite', 'bhindi', ['hot', 'mild'], 'medium', 'low', 'both'],
    ['Aloo Gobi', 'vegetables', 'Winter staple', 'aloogobi', ['cold', 'mild'], 'quick', 'low', 'both'],
    ['Aloo Palak', 'vegetables', null, null, ['cold', 'mild'], 'quick', 'low', 'both'],
    ['Karela', 'vegetables', null, null, ['hot'], 'medium', 'low', 'both'],
    ['Saag', 'vegetables', 'Winter classic', null, ['cold'], 'heavy', 'low', 'both'],
    ['Mixed Vegetables', 'vegetables', null, null, ['any'], 'quick', 'low', 'both'],
    ['Aloo Matar', 'vegetables', null, null, ['cold', 'mild'], 'quick', 'low', 'both'],
    ['Baingan Bharta', 'vegetables', null, null, ['mild', 'cold'], 'medium', 'low', 'both'],

    // Lentils
    ['Daal Chawal', 'lentils', 'Comfort classic', 'lentils', ['hot', 'mild'], 'quick', 'low', 'both'],
    ['Daal Mash', 'lentils', null, null, ['any'], 'quick', 'low', 'both'],
    ['Chana Daal', 'lentils', null, null, ['cold', 'rainy'], 'quick', 'low', 'both'],
    ['Daal Mong', 'lentils', 'Light and easy', null, ['hot'], 'quick', 'low', 'both'],
    ['Lobia', 'lentils', null, null, ['mild'], 'medium', 'low', 'both'],
    ['Masoor Daal', 'lentils', null, null, ['any'], 'quick', 'low', 'both'],
    ['Safaid Chanay', 'lentils', null, null, ['cold', 'mild'], 'medium', 'low', 'both'],
    ['Karhi Pakora', 'lentils', 'Rainy-day favourite', 'karhi', ['rainy', 'mild'], 'medium', 'low', 'both'],

    // Chicken
    ['Chicken Karahi', 'chicken', 'A weekday treat', 'karahi', ['any'], 'medium', 'medium', 'dinner'],
    ['Chicken Qorma', 'chicken', null, null, ['cold', 'mild'], 'heavy', 'medium', 'dinner'],
    ['Chicken Handi', 'chicken', null, null, ['mild', 'cold'], 'medium', 'medium', 'dinner'],
    ['Chicken Jalfrezi', 'chicken', null, null, ['any'], 'quick', 'medium', 'both'],
    ['Aloo Chicken', 'chicken', null, null, ['cold', 'rainy'], 'medium', 'medium', 'both'],
    ['Palak Chicken', 'chicken', null, null, ['cold'], 'medium', 'medium', 'both'],

    // Beef
    ['Qeema', 'beef', null, 'qeema', ['any'], 'quick', 'medium', 'both'],
    ['Nihari', 'beef', 'Weekend special', null, ['cold'], 'heavy', 'high', 'both'],
    ['Aloo Qeema', 'beef', null, null, ['mild'], 'quick', 'medium', 'both'],
    ['Haleem', 'beef', null, null, ['cold', 'rainy'], 'heavy', 'medium', 'dinner'],
    ['Kofta Curry', 'beef', null, null, ['cold'], 'medium', 'medium', 'dinner'],
    ['Aloo Gosht', 'beef', 'Sunday lunch', null, ['cold', 'rainy'], 'heavy', 'high', 'lunch'],

    // Mutton
    ['Mutton Karahi', 'mutton', null, null, ['cold'], 'medium', 'high', 'dinner'],
    ['Mutton Qorma', 'mutton', null, null, ['cold'], 'heavy', 'high', 'dinner'],
    ['Paya', 'mutton', null, null, ['cold'], 'heavy', 'medium', 'both'],

    // Fish
    ['Fish Fry', 'fish', 'Winter favourite', 'fish', ['cold', 'rainy'], 'medium', 'high', 'dinner'],
    ['Fish Curry', 'fish', null, 'fish', ['cold', 'mild'], 'medium', 'high', 'both'],

    // Rice
    ['Biryani', 'rice', 'Weekend centrepiece', 'biryani', ['any'], 'heavy', 'medium', 'both'],
    ['Matar Pulao', 'rice', null, null, ['mild', 'cold'], 'medium', 'low', 'both'],
    ['Yakhni Pulao', 'rice', null, null, ['cold', 'rainy'], 'heavy', 'medium', 'both'],
    ['Khichri', 'rice', 'Easy on the stomach', null, ['rainy', 'cold'], 'quick', 'low', 'dinner'],

    // Breakfast
    ['Anda Paratha', 'breakfast', null, 'paratha', ['cold', 'mild'], 'quick', 'low', 'breakfast'],
    ['Halwa Puri', 'breakfast', 'Sunday breakfast', null, ['cold'], 'heavy', 'medium', 'breakfast'],
    ['Aloo Paratha', 'breakfast', null, null, ['cold', 'mild'], 'medium', 'low', 'breakfast'],

    // Snacks
    ['Pakoras', 'snacks', 'Rainy-day treat', null, ['rainy'], 'quick', 'low', 'both'],
    ['Samosas', 'snacks', null, null, ['rainy', 'cold'], 'medium', 'low', 'both'],
];
