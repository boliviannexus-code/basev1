package com.nexgol.biometric;

import java.nio.file.Files;
import java.nio.file.Path;
import java.util.ArrayList;
import java.util.Base64;
import java.util.Comparator;
import java.util.List;

import com.machinezoo.sourceafis.FingerprintImage;
import com.machinezoo.sourceafis.FingerprintImageOptions;
import com.machinezoo.sourceafis.FingerprintMatcher;
import com.machinezoo.sourceafis.FingerprintTemplate;
import com.google.gson.Gson;
import com.google.gson.reflect.TypeToken;

public final class SourceAfisCli {
    private static final Gson gson = new Gson();
    private static final Base64.Encoder b64Encoder = Base64.getEncoder();
    private static final Base64.Decoder b64Decoder = Base64.getDecoder();

    private SourceAfisCli() {
    }

    public static void main(String[] args) throws Exception {
        if (args.length < 1) {
            throw new IllegalArgumentException("Usage: SourceAfisCli <command> ...");
        }

        switch (args[0]) {
            case "create-template" -> createTemplate(args);
            case "compare-images" -> compareImages(args);
            case "compare-templates" -> compareTemplates(args);
            case "identify-templates" -> identifyTemplates(args);
            default -> throw new IllegalArgumentException("Unknown command: " + args[0]);
        }
    }

    private static void createTemplate(String[] args) throws Exception {
        if (args.length != 2) {
            throw new IllegalArgumentException("Usage: SourceAfisCli create-template <png>");
        }

        System.out.println(b64Encoder.encodeToString(template(Path.of(args[1])).toByteArray()));
    }

    private static void compareImages(String[] args) throws Exception {
        if (args.length != 3) {
            throw new IllegalArgumentException("Usage: SourceAfisCli compare-images <stored_png> <candidate_png>");
        }

        double score = score(template(Path.of(args[1])), template(Path.of(args[2])));

        System.out.printf("%.6f%n", score);
    }

    private static void compareTemplates(String[] args) {
        if (args.length != 3) {
            throw new IllegalArgumentException("Usage: SourceAfisCli compare-templates <stored_template_base64> <candidate_template_base64>");
        }

        double score = score(template(args[1]), template(args[2]));

        System.out.printf("%.6f%n", score);
    }

    private static void identifyTemplates(String[] args) throws Exception {
        if (args.length != 3) {
            throw new IllegalArgumentException("Usage: SourceAfisCli identify-templates <candidate_template_base64> <templates_json>");
        }

        var candidate = template(args[1]);
        var json = Files.readString(Path.of(args[2]));
        var type = new TypeToken<List<StoredTemplate>>() {}.getType();
        List<StoredTemplate> storedTemplates = gson.fromJson(json, type);
        List<MatchResult> results = new ArrayList<>();

        for (var storedTemplate : storedTemplates) {
            double score = score(template(storedTemplate.template), candidate);
            results.add(new MatchResult(storedTemplate.id, storedTemplate.finger_position, score));
        }

        results.sort(Comparator.comparingDouble((MatchResult result) -> result.score).reversed());

        System.out.println(gson.toJson(results));
    }

    private static FingerprintTemplate template(Path path) throws Exception {
        byte[] image = Files.readAllBytes(path);
        var options = new FingerprintImageOptions().dpi(500);

        return new FingerprintTemplate(new FingerprintImage(image, options));
    }

    private static FingerprintTemplate template(String encoded) {
        return new FingerprintTemplate(b64Decoder.decode(encoded));
    }

    private static double score(FingerprintTemplate stored, FingerprintTemplate candidate) {
        return new FingerprintMatcher(stored).match(candidate);
    }

    private static final class StoredTemplate {
        long id;
        String finger_position;
        String template;
    }

    private static final class MatchResult {
        long id;
        String finger_position;
        double score;

        MatchResult(long id, String fingerPosition, double score) {
            this.id = id;
            this.finger_position = fingerPosition;
            this.score = score;
        }
    }
}
