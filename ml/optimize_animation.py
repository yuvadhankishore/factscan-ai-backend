path = r'C:\Users\SCAD\StudioProjects\FactScanAI\app\src\main\java\com\simats\factscanai\ui\components\SplitScreenFakeNewsAnimation.kt'
with open(path, 'rb') as f:
    data = f.read()

# Target 1: Gate animations on isPlaying
target1 = """    // Infinite laser and pulse transitions
    val infiniteTransition = rememberInfiniteTransition(label = "split_scan")
    val laserY by infiniteTransition.animateFloat(
        initialValue = 0f,
        targetValue = 1f,
        animationSpec = infiniteRepeatable(
            animation = tween(2000, easing = LinearEasing),
            repeatMode = RepeatMode.Reverse
        ),
        label = "laser_y"
    )

    val pulseAlpha by infiniteTransition.animateFloat(
        initialValue = 0.3f,
        targetValue = 1f,
        animationSpec = infiniteRepeatable(
            animation = tween(900, easing = FastOutSlowInEasing),
            repeatMode = RepeatMode.Reverse
        ),
        label = "pulse_alpha"
    )""".replace('\r\n', '\n').replace('\n', '\r\n').encode('utf-8')

replacement1 = """    // Infinite laser and pulse transitions (only animated during active playback to preserve 120fps)
    val infiniteTransition = rememberInfiniteTransition(label = "split_scan")
    val laserY by if (isPlaying) {
        infiniteTransition.animateFloat(
            initialValue = 0f,
            targetValue = 1f,
            animationSpec = infiniteRepeatable(
                animation = tween(2000, easing = LinearEasing),
                repeatMode = RepeatMode.Reverse
            ),
            label = "laser_y"
        )
    } else {
        remember { mutableFloatStateOf(0.5f) }
    }

    val pulseAlpha by if (isPlaying) {
        infiniteTransition.animateFloat(
            initialValue = 0.3f,
            targetValue = 1f,
            animationSpec = infiniteRepeatable(
                animation = tween(900, easing = FastOutSlowInEasing),
                repeatMode = RepeatMode.Reverse
            ),
            label = "pulse_alpha"
        )
    } else {
        remember { mutableFloatStateOf(0.6f) }
    }""".replace('\r\n', '\n').replace('\n', '\r\n').encode('utf-8')

# Target 2: Pass lambdas to avoid recomposition
target2 = """                    UnverifiedClaimPane(
                        headline = headlineText,
                        laserY = laserY,
                        pulseAlpha = pulseAlpha,
                        scanStep = scanStep,
                        isFakeNews = isFakeNews
                    )
                }

                // Center Horizontal Split Beam
                SplitDividerBeam(
                    pulseAlpha = pulseAlpha
                )""".replace('\r\n', '\n').replace('\n', '\r\n').encode('utf-8')

replacement2 = """                    UnverifiedClaimPane(
                        headline = headlineText,
                        laserY = { laserY },
                        pulseAlpha = { pulseAlpha },
                        scanStep = scanStep,
                        isFakeNews = isFakeNews
                    )
                }

                // Center Horizontal Split Beam
                SplitDividerBeam(
                    pulseAlpha = { pulseAlpha }
                )""".replace('\r\n', '\n').replace('\n', '\r\n').encode('utf-8')

# Target 3: UnverifiedClaimPane signature and canvas reading
target3 = """@Composable
fun UnverifiedClaimPane(
    headline: String,
    laserY: Float,
    pulseAlpha: Float,
    scanStep: Int,
    isFakeNews: Boolean
) {
    Box(modifier = Modifier.fillMaxSize()) {
        Column {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "🔴 CLAIM INPUT SIDE",
                    color = Color(0xFFEF4444),
                    fontSize = 11.sp,
                    fontWeight = FontWeight.ExtraBold,
                    letterSpacing = 1.sp
                )

                Box(
                    modifier = Modifier
                        .clip(RoundedCornerShape(8.dp))
                        .background(Color(0xFF7F1D1D))
                        .padding(horizontal = 6.dp, vertical = 2.dp)
                ) {
                    Text(text = "UNVERIFIED", color = Color(0xFFFCA5A5), fontSize = 9.sp, fontWeight = FontWeight.Bold)
                }
            }

            Spacer(modifier = Modifier.height(8.dp))

            Text(
                text = "\"$headline\"",
                color = Color.White,
                fontSize = 13.sp,
                fontWeight = FontWeight.SemiBold,
                lineHeight = 18.sp
            )

            Spacer(modifier = Modifier.height(8.dp))

            // Suspicious flags highlights
            if (scanStep >= 2) {
                Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                    if (isFakeNews) {
                        FlagTag(text = "⚠️ Sensational Phrase: 'Earth go dark'", color = Color(0xFFF87171))
                        FlagTag(text = "⚠️ Uncited Source: No official release", color = Color(0xFFFBBF24))
                    } else {
                        FlagTag(text = "✓ Verified Publisher Domain", color = Color(0xFF34D399))
                        FlagTag(text = "✓ Official Institutional Attribution", color = Color(0xFF60A5FA))
                    }
                }
            }
        }

        // Animated Laser Overlay
        Canvas(modifier = Modifier.fillMaxSize()) {
            val y = size.height * laserY
            drawLine(
                brush = Brush.horizontalGradient(
                    colors = listOf(
                        Color.Transparent,
                        Color(0xFFEF4444).copy(alpha = pulseAlpha),
                        Color(0xFFF87171),
                        Color(0xFFEF4444).copy(alpha = pulseAlpha),
                        Color.Transparent
                    )
                ),
                start = Offset(0f, y),
                end = Offset(size.width, y),
                strokeWidth = 3.dp.toPx()
            )
        }
    }
}""".replace('\r\n', '\n').replace('\n', '\r\n').encode('utf-8')

replacement3 = """@Composable
fun UnverifiedClaimPane(
    headline: String,
    laserY: () -> Float,
    pulseAlpha: () -> Float,
    scanStep: Int,
    isFakeNews: Boolean
) {
    Box(modifier = Modifier.fillMaxSize()) {
        Column {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "🔴 CLAIM INPUT SIDE",
                    color = Color(0xFFEF4444),
                    fontSize = 11.sp,
                    fontWeight = FontWeight.ExtraBold,
                    letterSpacing = 1.sp
                )

                Box(
                    modifier = Modifier
                        .clip(RoundedCornerShape(8.dp))
                        .background(Color(0xFF7F1D1D))
                        .padding(horizontal = 6.dp, vertical = 2.dp)
                ) {
                    Text(text = "UNVERIFIED", color = Color(0xFFFCA5A5), fontSize = 9.sp, fontWeight = FontWeight.Bold)
                }
            }

            Spacer(modifier = Modifier.height(8.dp))

            Text(
                text = "\"$headline\"",
                color = Color.White,
                fontSize = 13.sp,
                fontWeight = FontWeight.SemiBold,
                lineHeight = 18.sp
            )

            Spacer(modifier = Modifier.height(8.dp))

            // Suspicious flags highlights
            if (scanStep >= 2) {
                Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                    if (isFakeNews) {
                        FlagTag(text = "⚠️ Sensational Phrase: 'Earth go dark'", color = Color(0xFFF87171))
                        FlagTag(text = "⚠️ Uncited Source: No official release", color = Color(0xFFFBBF24))
                    } else {
                        FlagTag(text = "✓ Verified Publisher Domain", color = Color(0xFF34D399))
                        FlagTag(text = "✓ Official Institutional Attribution", color = Color(0xFF60A5FA))
                    }
                }
            }
        }

        // Animated Laser Overlay - reads lambda directly inside draw scope (zero recomposition)
        Canvas(modifier = Modifier.fillMaxSize()) {
            val y = size.height * laserY()
            val alpha = pulseAlpha()
            drawLine(
                brush = Brush.horizontalGradient(
                    colors = listOf(
                        Color.Transparent,
                        Color(0xFFEF4444).copy(alpha = alpha),
                        Color(0xFFF87171),
                        Color(0xFFEF4444).copy(alpha = alpha),
                        Color.Transparent
                    )
                ),
                start = Offset(0f, y),
                end = Offset(size.width, y),
                strokeWidth = 3.dp.toPx()
            )
        }
    }
}""".replace('\r\n', '\n').replace('\n', '\r\n').encode('utf-8')

# Target 4: SplitDividerBeam signature and canvas reading
target4 = """@Composable
fun SplitDividerBeam(
    pulseAlpha: Float
) {
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .height(12.dp)
            .background(Color(0xFF0F172A)),
        contentAlignment = Alignment.Center
    ) {
        Canvas(modifier = Modifier.fillMaxSize()) {
            drawLine(
                color = Color(0xFF38BDF8).copy(alpha = pulseAlpha),
                start = Offset(0f, size.height / 2f),
                end = Offset(size.width, size.height / 2f),
                strokeWidth = 2.dp.toPx()
            )
        }
    }
}""".replace('\r\n', '\n').replace('\n', '\r\n').encode('utf-8')

replacement4 = """@Composable
fun SplitDividerBeam(
    pulseAlpha: () -> Float
) {
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .height(12.dp)
            .background(Color(0xFF0F172A)),
        contentAlignment = Alignment.Center
    ) {
        Canvas(modifier = Modifier.fillMaxSize()) {
            drawLine(
                color = Color(0xFF38BDF8).copy(alpha = pulseAlpha()),
                start = Offset(0f, size.height / 2f),
                end = Offset(size.width, size.height / 2f),
                strokeWidth = 2.dp.toPx()
            )
        }
    }
}""".replace('\r\n', '\n').replace('\n', '\r\n').encode('utf-8')

print("target1 in data:", target1 in data)
print("target2 in data:", target2 in data)
print("target3 in data:", target3 in data)
print("target4 in data:", target4 in data)

if target1 in data and target2 in data and target3 in data and target4 in data:
    data = data.replace(target1, replacement1, 1)
    data = data.replace(target2, replacement2, 1)
    data = data.replace(target3, replacement3, 1)
    data = data.replace(target4, replacement4, 1)
    with open(path, 'wb') as f:
        f.write(data)
    print("SUCCESS: SplitScreen animations optimized for zero recomposition!")
else:
    print("FAILURE: Targets not found")
